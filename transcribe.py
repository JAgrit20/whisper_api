import os
import time
import mysql.connector
import whisper
import json

def transcribe_audio(file_path, language=None):
    try:
        model = whisper.load_model("tiny", device="cpu")
        audio = whisper.load_audio(file_path)
        result = model.transcribe(audio, language=language) if language else model.transcribe(audio)
        return {"transcription": result["text"]}
    except Exception as e:
        return {"error": str(e)}
    

def process_queue():
    while True:
        try:
            # Connect to the database
            conn = mysql.connector.connect(
                host="localhost",
                user="root",
                password="Athabasca@123",
                database="notetakers"
            )
            cursor = conn.cursor(dictionary=True)

            # Fetch the next queued recording
            cursor.execute("SELECT * FROM recordings WHERE status = 'queued' ORDER BY created_at LIMIT 1")
            recording = cursor.fetchone()

            if recording:
                # Mark as processing
                cursor.execute("UPDATE recordings SET status = 'processing' WHERE id = %s", (recording['id'],))
                conn.commit()

                # Transcribe the audio
                file_path = recording['file_path']
                language = recording['language']
                result = transcribe_audio(file_path, language)

                if "transcription" in result:
                    # Update the database with the transcription
                    cursor.execute(
                        "UPDATE recordings SET status = 'completed', transcription = %s WHERE id = %s",
                        (result['transcription'], recording['id'])
                    )
                else:
                    # Handle errors
                    cursor.execute(
                        "UPDATE recordings SET status = 'failed', transcription = %s WHERE id = %s",
                        (result['error'], recording['id'])
                    )

                conn.commit()

            else:
                # No queued recordings, wait and retry
                time.sleep(5)

        except Exception as e:
            print(f"Error: {str(e)}")
        finally:
            cursor.close()
            conn.close()

if __name__ == "__main__":
    process_queue()
