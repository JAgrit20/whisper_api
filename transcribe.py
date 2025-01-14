import os
import time
import mysql.connector
import whisper
import json

def transcribe_audio(file_path, language=None):
    try:
        print(f"DEBUG: Starting transcription for file: {file_path} with language: {language}")
        model = whisper.load_model("tiny", device="cpu")
        print("DEBUG: Whisper model loaded successfully")
        audio = whisper.load_audio(file_path)
        print(f"DEBUG: Audio file loaded successfully: {file_path}")
        result = model.transcribe(audio, language=language) if language else model.transcribe(audio)
        print("DEBUG: Transcription completed successfully")
        return {"transcription": result["text"]}
    except Exception as e:
        print(f"ERROR: Transcription failed with error: {str(e)}")
        return {"error": str(e)}

def process_queue():
    while True:
        try:
            print("DEBUG: Connecting to the database")
            conn = mysql.connector.connect(
                host="localhost",
                user="root",
                password="Athabasca@123",
                database="notetakers"
            )
            cursor = conn.cursor(dictionary=True)
            print("DEBUG: Database connection established successfully")

            # Fetch the next queued recording
            print("DEBUG: Fetching next queued recording")
            cursor.execute("SELECT * FROM recordings WHERE status = 'queued' ORDER BY created_at LIMIT 1")
            recording = cursor.fetchone()

            if recording:
                print(f"DEBUG: Found a recording to process: {recording}")
                # Mark as processing
                cursor.execute("UPDATE recordings SET status = 'processing' WHERE id = %s", (recording['id'],))
                conn.commit()
                print(f"DEBUG: Updated status to 'processing' for recording ID: {recording['id']}")

                # Transcribe the audio
                file_path = recording['file_path']
                language = recording['language']
                print(f"DEBUG: Starting transcription for file: {file_path}")
                result = transcribe_audio(file_path, language)

                if "transcription" in result:
                    print(f"DEBUG: Transcription successful: {result['transcription']}")
                    # Update the database with the transcription
                    cursor.execute(
                        "UPDATE recordings SET status = 'completed', transcription = %s WHERE id = %s",
                        (result['transcription'], recording['id'])
                    )
                else:
                    print(f"DEBUG: Transcription failed with error: {result['error']}")
                    # Handle errors
                    cursor.execute(
                        "UPDATE recordings SET status = 'failed', transcription = %s WHERE id = %s",
                        (result['error'], recording['id'])
                    )

                conn.commit()
                print(f"DEBUG: Database updated successfully for recording ID: {recording['id']}")

            else:
                print("DEBUG: No queued recordings found, retrying in 5 seconds")
                time.sleep(5)

        except Exception as e:
            print(f"ERROR: An error occurred: {str(e)}")
        finally:
            if 'cursor' in locals():
                cursor.close()
                print("DEBUG: Database cursor closed")
            if 'conn' in locals():
                conn.close()
                print("DEBUG: Database connection closed")

if __name__ == "__main__":
    print("DEBUG: Starting process_queue function")
    process_queue()
