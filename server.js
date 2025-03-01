// Original path: Users/michaelfarrugia/.pub-cache/hosted/pub.dev/workmanager-0.5.2/android/src/main/kotlin/dev/fluttercommunity/workmanager/BackgroundWorker.kt

package dev.fluttercommunity.workmanager

import android.content.Context
import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.work.Worker
import androidx.work.WorkerParameters
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.embedding.engine.dart.DartExecutor
import io.flutter.embedding.engine.plugins.FlutterPlugin
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import io.flutter.view.FlutterCallbackInformation
import java.util.concurrent.CountDownLatch
import java.util.concurrent.atomic.AtomicBoolean

// Temporary implementation - you might need to adapt this to your needs
class FlutterEnginePluginRegistry(private val flutterEngine: FlutterEngine) {
    fun add(plugin: FlutterPlugin) {
        flutterEngine.plugins.add(plugin)
    }
}

class BackgroundWorker(private val ctx: Context, private val params: WorkerParameters) : Worker(ctx, params) {
    private val TAG = "BackgroundWorker"

    companion object {
        private val isolateStarted = AtomicBoolean(false)
        private var backgroundChannel: MethodChannel? = null
        private var engine: FlutterEngine? = null

        fun startBackgroundIsolate(context: Context, callbackHandle: Long) {
            if (isolateStarted.get() || engine != null) {
                Log.d("BackgroundWorker", "Background isolate already started or engine was not null.")
                return
            }

            val appBundlePath = FlutterIsolateStartup.findAppBundlePath(context)
            val flutterCallback = FlutterCallbackInformation.lookupCallbackInformation(callbackHandle)
            if (flutterCallback == null) {
                Log.e("BackgroundWorker", "Fatal: failed to find callback")
                return
            }

            val engineLoader = FlutterIsolateStartup(context, appBundlePath, flutterCallback)
            engine = engineLoader.engine
            backgroundChannel = engineLoader.channel
            isolateStarted.set(true)
        }

        // Make sure the UI thread is utilized to call the plugins
        fun registerAfterTaskPlugin(registrar: FlutterEnginePluginRegistry) {
            Handler(Looper.getMainLooper()).post {
                registrar.add(WorkmanagerPlugin())
            }
        }
    }

    override fun doWork(): Result {
        return withPluginSettings { settings ->
            val callbackHandle = inputData.getLong("be.tramckrijte.workmanager.DART_TASK_CALLBACK_HANDLE_KEY", 0)
            val callbackInfo = FlutterCallbackInformation.lookupCallbackInformation(callbackHandle)
            val dartTask = DartTask(callbackInfo.callbackName, callbackInfo.callbackClassName)
            val isInDebugMode = inputData.getBoolean("be.tramckrijte.workmanager.IS_IN_DEBUG_MODE", false)

            return@withPluginSettings DartCallHandler(
                    ctx,
                    engineProvider,
                    backgroundChannelProvider,
                    settings.delegates).handle(dartTask, inputData)
        }
    }

    private fun <T> withPluginSettings(action: (settings: WorkmanagerPlugin.PluginSettings) -> T): T {
        val settings = WorkmanagerPlugin.PluginSettings()
        Log.d(TAG, "Using plugin settings: $settings")
        return action.invoke(settings)
    }

    private val backgroundChannelProvider: () -> MethodChannel? = {
        backgroundChannel
    }

    private val engineProvider: () -> FlutterEngine? = {
        engine
    }
}

class DartCallHandler(private val context: Context,
                      private val engineProvider: () -> FlutterEngine?,
                      private val backgroundChannelProvider: () -> MethodChannel?,
                      private val delegates: MutableSet<WorkmanagerCallHandler>) {

    private val TAG = "DartCallHandler"

    private val isCallbackDispatcherInitialized: Boolean
        get() = WorkmanagerPlugin.pluginRegistryCallback != null
                && engineProvider() != null
                && backgroundChannelProvider() != null

    fun handle(task: DartTask, inputData: androidx.work.Data): Result {
        try {
            if (!isCallbackDispatcherInitialized) {
                Log.e(TAG, "Fatal: DartCallHandler instance is not properly initialized. " +
                        "Bailing out. Did you call WorkmanagerPlugin.initialize?")
                return Result.failure()
            }

            val latch = CountDownLatch(1)
            var result = Result.retry()
            val backgroundChannel = backgroundChannelProvider()!!

            // Add additional logging for debugging
            Log.d(TAG, "Task info received. Task name: ${task.name}, class: ${task.className}")

            // First, execute the task on the background thread
            Handler(Looper.getMainLooper()).post {
                val callback = WorkmanagerPlugin.pluginRegistryCallback ?: run {
                    Log.e(TAG, "Plugin registry callback is null")
                    latch.countDown()
                    return@post
                }

                val engine = engineProvider() ?: run {
                    Log.e(TAG, "Flutter engine is null")
                    latch.countDown()
                    return@post
                }

                // Register plugins to the engine
                try {
                    val registry = FlutterEnginePluginRegistry(engine)
                    BackgroundWorker.registerAfterTaskPlugin(registry)
                } catch (e: Exception) {
                    Log.e(TAG, "Failed to register plugins", e)
                    latch.countDown()
                    return@post
                }

                Log.d(TAG, "Invoking background task: ${task.name}")
                backgroundChannel.invokeMethod("", mapOf(
                        "task_id" to inputData.getString("be.tramckrijte.workmanager.DART_TASK_ID_KEY"),
                        "task" to task.name,
                        "job_handle" to inputData.getLong("be.tramckrijte.workmanager.DART_TASK_CALLBACK_HANDLE_KEY", 0)
                ), object : MethodChannel.Result {
                    override fun success(resultObj: Any?) {
                        Log.d(TAG, "Background task successful: ${task.name}")
                        result = Result.success()
                        latch.countDown()
                    }

                    override fun error(errorCode: String, errorMessage: String?, errorDetails: Any?) {
                        Log.e(TAG, "Background task error: ${task.name}, error: $errorMessage")
                        result = Result.failure()
                        latch.countDown()
                    }

                    override fun notImplemented() {
                        result = Result.failure()
                        latch.countDown()
                    }
                })
            }

            latch.await()
            return result
        } catch (e: Exception) {
            Log.e(TAG, "Exception encountered while executing dart call", e)
            return Result.failure()
        }
    }
}