import os

# Must set this before importing whisper or torch, so that the cache folder is used properly
os.environ["XDG_CACHE_HOME"] = "/var/www/.cache"

import sys
import json
import whisper

def transcribe_audio(file_path):
    try:
        # Load Whisper model
        model = whisper.load_model("tiny", device="cpu")
        
        # Load audio file
        audio = whisper.load_audio(file_path)
        
        # Transcribe the audio
        result = model.transcribe(audio)
        
        # Return the transcription
        return {"transcription": result["text"]}
    except Exception as e:
        # Handle errors gracefully and return them in JSON format
        return {"error": str(e)}

if __name__ == "__main__":
    # Check if an audio file path is provided
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No audio file path provided"}))
        sys.exit(1)

    audio_file_path = sys.argv[1]
    
    # Perform transcription
    output = transcribe_audio(audio_file_path)
    
    # Print result as JSON
    print(json.dumps(output))
