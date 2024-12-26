#!/usr/bin/env python3
import sys
import json
from transformers import pipeline

def transcribe_audio(file_path):
    # Create an ASR pipeline using Whisper
    whisper_asr = pipeline(
        "automatic-speech-recognition",
        model="openai/whisper-medium"
    )
    result = whisper_asr(file_path)
    # The pipeline returns a dictionary with a 'text' key
    return result["text"]

if __name__ == "__main__":
    # Expect a single argument: path to the audio file
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No audio file path provided"}))
        sys.exit(1)

    audio_file_path = sys.argv[1]
    transcribed_text = transcribe_audio(audio_file_path)

    # Print the transcription in JSON format, so PHP can parse if needed
    print(json.dumps({"transcription": transcribed_text}))
