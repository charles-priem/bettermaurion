import requests
import json
from datetime import datetime, timedelta
import os
import sys

# Add project root to sys.path to allow imports from other directories
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Configuration
# Use a session for all requests
SESSION = requests.Session()

# --- User credentials ---
# It's better to use environment variables or a config file for this
AURION_USER = os.environ.get('AURION_USER', 'charles.priem@student.junia.com')
AURION_PASS = os.environ.get('AURION_PASS', '#')

# --- API and File Paths ---
API_BASE_URL = "http://localhost/ProjetBetterMoroomia/api/"
LOGIN_URL = f"{API_BASE_URL}login_aurion.php" # Assuming you have a login script
FETCH_URL = f"{API_BASE_URL}fetch_events.php"
OUTPUT_FILE = os.path.join(os.path.dirname(__file__), 'data', 'plannings_promotions.json')
PLANNING_IDS_FILE = os.path.join(os.path.dirname(__file__), 'data', 'planning_ids.json')

# Number of weeks to fetch from the current date
WEEKS_TO_FETCH = 52

def login_to_api():
    """Logs into the local API to establish a session with Aurion credentials."""
    print("Attempting to log in via local API...")
    try:
        payload = {'login': AURION_USER, 'password': AURION_PASS}
        response = SESSION.post(LOGIN_URL, data=payload)
        response.raise_for_status()
        result = response.json()
        if result.get('status') == 'success':
            print("Login successful.")
            return True
        else:
            print(f"Login failed: {result.get('message', 'Unknown error')}")
            return False
    except requests.exceptions.RequestException as e:
        print(f"Error during login request: {e}")
        return False
    except json.JSONDecodeError:
        print("Error decoding login response. Is the API running and returning valid JSON?")
        return False


def get_planning_ids():
    """Reads planningIdInit values from the JSON file."""
    try:
        with open(PLANNING_IDS_FILE, 'r', encoding='utf-8') as f:
            data = json.load(f)
            # Assuming the file is a list of objects with an 'id' key
            return [item['id'] for item in data if 'id' in item]
    except FileNotFoundError:
        print(f"Error: Planning ID file not found at {PLANNING_IDS_FILE}")
        return []
    except json.JSONDecodeError:
        print(f"Error: Could not decode JSON from {PLANNING_IDS_FILE}.")
        return []

def fetch_events_for_week(planning_id, week_offset):
    """Fetches events for a specific planning ID and week offset using the session."""
    params = {'planningIdInit': planning_id, 'week': week_offset}
    try:
        response = SESSION.get(FETCH_URL, params=params)
        response.raise_for_status()
        # Check for empty response
        if not response.text:
            print(f"Warning: Empty response for planningId {planning_id}, week {week_offset}")
            return None
        return response.json()
    except requests.exceptions.RequestException as e:
        print(f"Error fetching data for planningId {planning_id}, week {week_offset}: {e}")
    except json.JSONDecodeError:
        print(f"Error decoding JSON for planningId {planning_id}, week {week_offset}. Response: {response.text[:200]}...")
    return None

def main():
    """Main function to fetch all planning data and save it to a JSON file."""
    
    # First, log in to establish the session for subsequent requests
    if not login_to_api():
        print("Could not log in. Please check your credentials and API status. Exiting.")
        return

    planning_ids = get_planning_ids()
    if not planning_ids:
        print("No planning IDs found. Exiting.")
        return

    all_planning_data = {}

    total_ids = len(planning_ids)
    for i, planning_id in enumerate(planning_ids):
        print(f"Fetching for planning ID: {planning_id} ({i+1}/{total_ids})")
        all_planning_data[planning_id] = []
        
        for week in range(WEEKS_TO_FETCH):
            print(f"  - Week {week+1}/{WEEKS_TO_FETCH}...")
            events = fetch_events_for_week(planning_id, week)
            
            if events and 'error' not in events:
                # Add week information to each event for context
                for event in events:
                    event['week_offset'] = week
                all_planning_data[planning_id].extend(events)
            elif events and 'error' in events:
                print(f"  - API Error for week {week}: {events['error']}")
            else:
                # Handle case where events is None or empty
                print(f"  - No events returned for week {week}.")

    # Ensure the data directory exists
    os.makedirs(os.path.dirname(OUTPUT_FILE), exist_ok=True)

    # Write all collected events to the output file
    try:
        with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
            json.dump(all_planning_data, f, indent=2, ensure_ascii=False)
        
        total_events = sum(len(events) for events in all_planning_data.values())
        print(f"\nSuccessfully fetched and saved {total_events} events from {total_ids} plannings to {OUTPUT_FILE}")

    except IOError as e:
        print(f"Error writing to output file {OUTPUT_FILE}: {e}")

if __name__ == "__main__":
    # Check for credentials before running
    if AURION_USER == 'your_aurion_username' or AURION_PASS == 'your_aurion_password':
        print("Please set your Aurion credentials as environment variables (AURION_USER, AURION_PASS) or directly in the script.")
    else:
        main()