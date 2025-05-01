from gtts import gTTS
import threading
import os
import subprocess

def _play_audio(message):
    """Plays text-to-speech message using gTTS and mpg123 (non-blocking)."""
    try:
        filename = "welcome.mp3"
        tts = gTTS(text=message, lang='en')
        tts.save(filename)

        # Use system audio player
        subprocess.call(["mpg123", filename])

        os.remove(filename)
    except Exception as e:
        print(f"❌ TTS Error: {e}")

def speak_message(message):
    """Speak the message in a background thread (non-blocking)."""
    threading.Thread(target=_play_audio, args=(message,), daemon=True).start()

