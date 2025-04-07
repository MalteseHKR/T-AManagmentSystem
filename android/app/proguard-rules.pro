# GSON specific rules
-keepattributes Signature
-keepattributes *Annotation*
-dontwarn sun.misc.**
-keep class com.google.gson.** { *; }
-keep class * implements com.google.gson.TypeAdapter
-keep class * implements com.google.gson.TypeAdapterFactory
-keep class * implements com.google.gson.JsonSerializer
-keep class * implements com.google.gson.JsonDeserializer

# Flutter Local Notifications
-keep class com.dexterous.flutterlocalnotifications.** { *; }
-keep class com.dexterous.flutterlocalnotifications.FlutterLocalNotificationsPlugin { *; }
-keepclassmembers class com.dexterous.flutterlocalnotifications.** { *; }
-keep class androidx.core.app.** { *; }
-dontwarn androidx.core.app.**

-dontwarn org.tensorflow.lite.gpu.GpuDelegateFactory$Options