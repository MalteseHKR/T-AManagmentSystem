import pygame
import cv2
import numpy as np
import time
import os

# Path to stored images
TEST_FACES_FOLDER = "/home/cdadmin/remote_documents"

# Initialize Pygame
pygame.init()

# Define screen size (adjust based on your display)
SCREEN_WIDTH = 1366
SCREEN_HEIGHT = 768

# Create Pygame window
screen = pygame.display.set_mode((SCREEN_WIDTH, SCREEN_HEIGHT), pygame.FULLSCREEN)
pygame.display.set_caption("Face Recognition System")

# Colors
WHITE = (255, 255, 255)
BLACK = (0, 0, 0)
GREEN = (0, 255, 0)
RED = (255, 0, 0)

# Fonts
pygame.font.init()
font_large = pygame.font.Font(None, 50)
font_small = pygame.font.Font(None, 30)

# Store last punch details
last_punch_name = None
last_punch_time = 0
last_punch_image = None  # Stores the image of the last punched person

# Track consecutive "Unknown" attempts
unknown_attempts = 0
max_unknown_attempts = 20  # Threshold before displaying HR message
show_hr_message = False
hr_message_time = 0  # Time when HR message was displayed

def resize_frame(frame, target_width, target_height):
    """✅ Resize frame while maintaining aspect ratio without black padding."""
    h, w, _ = frame.shape
    aspect_ratio = w / h

    scale_factor = min(target_width / w, target_height / h)

    new_width = int(w * scale_factor)
    new_height = int(h * scale_factor)

    return cv2.resize(frame, (new_width, new_height)), new_width, new_height

def convert_opencv_to_pygame(frame):
    """✅ Converts OpenCV frame (BGR) to Pygame surface (RGB) while keeping aspect ratio."""
    frame_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
    frame_surface = pygame.surfarray.make_surface(frame_rgb)
    return frame_surface

def fix_orientation(frame):
    """✅ Flips image horizontally & rotates 90 degrees counterclockwise to fix incorrect orientation."""
    frame = cv2.flip(frame, 1)
    frame = cv2.rotate(frame, cv2.ROTATE_90_COUNTERCLOCKWISE)
    return frame

def load_punch_image(name):
    """✅ Loads the stored image of the detected person if available."""
    global last_punch_image
    file_path = os.path.join(TEST_FACES_FOLDER, f"{name}.jpg")

    if os.path.exists(file_path):
        image = cv2.imread(file_path)
        image = cv2.rotate(image, cv2.ROTATE_90_COUNTERCLOCKWISE)
        image, _, _ = resize_frame(image, SCREEN_WIDTH // 5, SCREEN_HEIGHT // 5)
        last_punch_image = convert_opencv_to_pygame(image)
    else:
        last_punch_image = None  # Reset if no image found
        
def show_punch_alert(name):
    """✅ Displays a punch-in alert and logs it."""
    global last_punch_name, last_punch_time
    print(f"🔔 Showing punch-in alert for: {name}")  # ✅ Debugging print
    last_punch_name = name
    last_punch_time = time.time()
    load_punch_image(name)  # ✅ Load user's image for UI display


def display_combined_frame(frame, thermal_frame, face_names):
    """✅ Displays the video feed using Pygame, showing the HR notice if needed."""
    global last_punch_name, last_punch_time, last_punch_image, unknown_attempts, show_hr_message, hr_message_time

    frame = fix_orientation(frame)
    thermal_frame = fix_orientation(thermal_frame)

    target_width = SCREEN_WIDTH // 2
    target_height = SCREEN_HEIGHT - 80

    frame_resized, _, _ = resize_frame(frame, target_width, target_height)
    thermal_resized, _, _ = resize_frame(thermal_frame, target_width, target_height)

    frame_surface = convert_opencv_to_pygame(frame_resized)
    thermal_surface = convert_opencv_to_pygame(thermal_resized)

    # ✅ Handle Pygame Events
    for event in pygame.event.get():
        if event.type == pygame.QUIT:
            pygame.quit()
            return

    screen.fill(BLACK)
    screen.blit(frame_surface, (0, 80))
    screen.blit(thermal_surface, (SCREEN_WIDTH // 2, 80))

    pygame.draw.rect(screen, BLACK, (0, 0, SCREEN_WIDTH, 80))
    text_surface = font_large.render("Please Look at the Camera...", True, WHITE)
    screen.blit(text_surface, (50, 25))

    y_offset = 100
    for name in face_names:
        if name == "Unknown":
            unknown_attempts += 1
            if unknown_attempts >= max_unknown_attempts:
                print("🚨 User not found, displaying HR message!")
                show_hr_message = True
                hr_message_time = time.time()  # Track when the message is shown
        else:
            unknown_attempts = 0  # Reset if a known user is detected
            face_text = font_small.render(f"{name} Detected", True, GREEN)
            screen.blit(face_text, (50, y_offset))
            y_offset += 40

        color = GREEN if name != "Unknown" else RED
        face_text = font_small.render(f"{name} Detected", True, color)
        screen.blit(face_text, (50, y_offset))
        y_offset += 40
        
    # ✅ Dynamic Punch-In Alert with Stored Image (Now 5 Seconds)
    current_time = time.time()
    if last_punch_name and (current_time - last_punch_time) < 5:  # Show for 5 seconds
        text_width, text_height = font_large.size(last_punch_name)  # Get dynamic text size
        alert_width = max(text_width + 100, 300)  # Make alert box width based on text
        alert_height = SCREEN_HEIGHT // 3  # Make it bigger to fit the image
        alert_x = (SCREEN_WIDTH - alert_width) // 2
        alert_y = SCREEN_HEIGHT // 3

        # ✅ Alert Box with Dynamic Size
        alert_surface = pygame.Surface((alert_width, alert_height), pygame.SRCALPHA)
        alert_surface.fill((0, 255, 0, 200))  # Green background with transparency
        screen.blit(alert_surface, (alert_x, alert_y))

        # ✅ Display punch-in name inside alert box (Centered)
        alert_text = font_large.render(f"{last_punch_name}", True, BLACK)
        screen.blit(alert_text, (alert_x + (alert_width - text_width) // 2, alert_y + 20))

        # ✅ Display stored image neatly BELOW the name inside the alert box
        if last_punch_image:
            img_x = alert_x + (alert_width - last_punch_image.get_width()) // 2
            img_y = alert_y + text_height + 40
            screen.blit(last_punch_image, (img_x, img_y))  # Centered below text
    # ✅ Show HR Message if threshold reached
    if show_hr_message:
        show_hr_notice()
        if time.time() - hr_message_time > 5:  # Display for 5 seconds
            show_hr_message = False  # Hide message after time expires

    pygame.display.flip()

def show_hr_notice():
    """✅ Displays a warning on the screen: 'User not found, please contact HR Department.'"""
    hr_message_text = "User not found, please contact HR Department."
    text_surface = font_large.render(hr_message_text, True, RED)
    text_rect = text_surface.get_rect(center=(SCREEN_WIDTH // 2, SCREEN_HEIGHT // 2))
    screen.blit(text_surface, text_rect)
    pygame.display.flip()


def close_pygame():
    """✅ Closes Pygame window safely."""
    pygame.quit()

