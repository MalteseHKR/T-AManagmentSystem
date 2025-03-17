import cv2

def display_combined_frame(frame, thermal_frame, face_names):
    # Resize frames to the desired dimensions
    frame_width = 640
    frame_height = 480
    visual_frame_resized = cv2.resize(frame, (frame_width, frame_height))
    thermal_frame_resized = cv2.resize(thermal_frame, (frame_width, frame_height))

    # Concatenate the visual and thermal frames horizontally
    combined_frame = cv2.hconcat([visual_frame_resized, thermal_frame_resized])

    # Display the combined frame with a message
    message = "Please look at the camera"
    font = cv2.FONT_HERSHEY_SIMPLEX
    cv2.putText(combined_frame, message, (10, 30), font, 1, (0, 255, 0), 2, cv2.LINE_AA)

    cv2.imshow('Combined Frame', combined_frame)
    if cv2.waitKey(1) & 0xFF == ord('q'):
        cv2.destroyAllWindows()