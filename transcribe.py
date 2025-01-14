import os
import time
import mysql.connector
import whisper
import json
import logging

# Configure logging
logging.basicConfig(
    filename='transcriber.log',  
    level=logging.DEBUG,            # Logging level
    format='%(asctime)s - %(levelname)s - %(message)s'
)

def transcribe_audio(file_path, language=None):
    try:
        logging.debug(f"Starting transcription for file: {file_path} with language: {language}")
        model = whisper.load_model("tiny", device="cpu")
        logging.debug("Whisper model loaded successfully")
        audio = whisper.load_audio(file_path)
        logging.debug(f"Audio file loaded successfully: {file_path}")
        result = model.transcribe(audio, language=language) if language else model.transcribe(audio)
        logging.debug("Transcription completed successfully")
        return {"transcription": result["text"]}
    except Exception as e:
        logging.error(f"Transcription failed with error: {str(e)}")
        return {"error": str(e)}

def process_queue():
    while True:
        try:
            logging.debug("Connecting to the database")
            conn = mysql.connector.connect(
                host="localhost",
                user="root",
                password="Athabasca@123",
                database="notetakers"
            )
            cursor = conn.cursor(dictionary=True)
            logging.debug("Database connection established successfully")

            # Fetch the next queued recording
            logging.debug("Fetching next queued recording")
            cursor.execute("SELECT * FROM recordings WHERE status = 'queued' ORDER BY created_at LIMIT 1")
            recording = cursor.fetchone()

            if recording:
                logging.debug(f"Found a recording to process: {recording}")
                # Mark as processing
                cursor.execute("UPDATE recordings SET status = 'processing' WHERE id = %s", (recording['id'],))
                conn.commit()
                logging.debug(f"Updated status to 'processing' for recording ID: {recording['id']}")

                # Transcribe the audio
                file_path = recording['file_path']
                language = recording['language']
                logging.debug(f"Starting transcription for file: {file_path}")
                result = transcribe_audio(file_path, language)

                if "transcription" in result:
                    logging.debug(f"Transcription successful: {result['transcription']}")
                    # Update the database with the transcription
                    cursor.execute(
                        "UPDATE recordings SET status = 'completed', transcription = %s WHERE id = %s",
                        (result['transcription'], recording['id'])
                    )
                else:
                    logging.debug(f"Transcription failed with error: {result['error']}")
                    # Handle errors
                    cursor.execute(
                        "UPDATE recordings SET status = 'failed', transcription = %s WHERE id = %s",
                        (result['error'], recording['id'])
                    )

                conn.commit()
                logging.debug(f"Database updated successfully for recording ID: {recording['id']}")

            else:
                logging.debug("No queued recordings found, retrying in 5 seconds")
                time.sleep(5)

        except Exception as e:
            logging.error(f"An error occurred: {str(e)}")
        finally:
            if 'cursor' in locals():
                cursor.close()
                logging.debug("Database cursor closed")
            if 'conn' in locals():
                conn.close()
                logging.debug("Database connection closed")

if __name__ == "__main__":
    logging.debug("Starting process_queue function")
    process_queue()
