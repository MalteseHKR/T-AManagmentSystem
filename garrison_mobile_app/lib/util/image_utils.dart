// lib/util/image_utils.dart
import 'dart:io';
import 'dart:typed_data';
import 'dart:ui' as ui;
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:path_provider/path_provider.dart';
import 'package:image/image.dart' as img;
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';

class ImageUtils {
  static const String TAG = "ImageUtils";
  
  // Crop face from image using the bounding box from face detection
  static Future<File?> cropFaceFromImage(File imageFile, Rect faceBounds) async {
    try {
      final bytes = await imageFile.readAsBytes();
      final image = img.decodeImage(bytes);
      
      if (image == null) {
        debugPrint('$TAG: Failed to decode image');
        return null;
      }
      
      // Ensure face bounds are within image dimensions
      final left = max(0, faceBounds.left.toInt());
      final top = max(0, faceBounds.top.toInt());
      final right = min(image.width, faceBounds.right.toInt());
      final bottom = min(image.height, faceBounds.bottom.toInt());
      
      // Check if we have a valid region to crop
      if (right <= left || bottom <= top) {
        debugPrint('$TAG: Invalid face crop region');
        return null;
      }
      
      // Add a margin around the face (20% on each side)
      final width = right - left;
      final height = bottom - top;
      
      final marginX = (width * 0.2).toInt();
      final marginY = (height * 0.2).toInt();
      
      final croppedLeft = max(0, left - marginX);
      final croppedTop = max(0, top - marginY);
      final croppedRight = min(image.width, right + marginX);
      final croppedBottom = min(image.height, bottom + marginY);
      
      // Crop the face
      final croppedImage = img.copyCrop(
        image, 
        x: croppedLeft, 
        y: croppedTop, 
        width: croppedRight - croppedLeft, 
        height: croppedBottom - croppedTop
      );
      
      // Save to temporary file
      final tempDir = await getTemporaryDirectory();
      final tempFile = File('${tempDir.path}/cropped_face_${DateTime.now().millisecondsSinceEpoch}.jpg');
      await tempFile.writeAsBytes(img.encodeJpg(croppedImage, quality: 100));
      
      return tempFile;
    } catch (e) {
      debugPrint('$TAG: Error cropping face: $e');
      return null;
    }
  }
  
  // Resize an image to the specified dimensions
  static Future<File?> resizeImage(File imageFile, int width, int height) async {
    try {
      final bytes = await imageFile.readAsBytes();
      final image = img.decodeImage(bytes);
      
      if (image == null) {
        debugPrint('$TAG: Failed to decode image');
        return null;
      }
      
      // Resize the image
      final resizedImage = img.copyResize(
        image,
        width: width,
        height: height,
        interpolation: img.Interpolation.linear
      );
      
      // Save to temporary file
      final tempDir = await getTemporaryDirectory();
      final tempFile = File('${tempDir.path}/resized_${width}x${height}_${DateTime.now().millisecondsSinceEpoch}.jpg');
      await tempFile.writeAsBytes(img.encodeJpg(resizedImage, quality: 100));
      
      return tempFile;
    } catch (e) {
      debugPrint('$TAG: Error resizing image: $e');
      return null;
    }
  }
  
  // Enhance image for face detection - with minimal but necessary processing
  static Future<File?> enhanceImageForFaceDetection(File imageFile) async {
    try {
      debugPrint('$TAG: Processing image for face detection with minimal changes: ${imageFile.path}');
      
      final bytes = await imageFile.readAsBytes();
      final image = img.decodeImage(bytes);
      
      if (image == null) {
        debugPrint('$TAG: Failed to decode image');
        return null;
      }
      
      var processedImage = image;
      
      // For iOS, apply very minimal enhancement to help with face detection
      // without significantly altering the image appearance
      if (Platform.isIOS) {
        // Very subtle contrast enhancement only for face detection purposes
        processedImage = img.adjustColor(
          image,
          contrast: 1.1,  // Very minimal contrast boost
          exposure: 0.05  // Very subtle exposure adjustment
        );
      }
      
      // Save to temporary file
      final tempDir = await getTemporaryDirectory();
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      final outputPath = '${tempDir.path}/processed_${timestamp}.jpg';
      
      // Use maximum quality to preserve original image
      final processedJpg = img.encodeJpg(processedImage, quality: 100);
      
      final outputFile = File(outputPath);
      await outputFile.writeAsBytes(processedJpg);
      
      debugPrint('$TAG: Processed image saved to: ${outputFile.path}');
      return outputFile;
    } catch (e) {
      debugPrint('$TAG: Error processing image: $e');
      return imageFile; // Return original on error
    }
  }
  
  // Convert UI Image to File
  static Future<File> uiImageToFile(ui.Image image) async {
    final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
    final pngBytes = byteData!.buffer.asUint8List();
    
    final tempDir = await getTemporaryDirectory();
    final tempFile = File('${tempDir.path}/image_${DateTime.now().millisecondsSinceEpoch}.png');
    await tempFile.writeAsBytes(pngBytes);
    
    return tempFile;
  }
  
  // Create a heat map overlay showing facial landmarks (for debugging)
  static ui.Image createFaceLandmarkDebugImage(img.Image image, List<Face> faces) {
    // TODO: Implementation for debugging
    // This would draw facial landmarks on a copy of the image
    return image as ui.Image;
  }
  
  // Platform-specific image preprocessing with minimal but necessary changes
  static Future<File?> preprocessImageForPlatform(File imageFile, {bool isForFaceDetection = true}) async {
    try {
      debugPrint('$TAG: Preprocessing image with minimal changes on ${Platform.isIOS ? "iOS" : "Android"}: ${imageFile.path}');
      
      final bytes = await imageFile.readAsBytes();
      final image = img.decodeImage(bytes);
      
      if (image == null) {
        debugPrint('$TAG: Failed to decode image');
        return imageFile; // Return original if processing fails
      }
      
      var processedImage = image;
      bool imageModified = false;
      
      // Check orientation on both platforms, most important for iOS
      bool needsRotation = image.width > image.height;
      
      if (needsRotation) {
        debugPrint('$TAG: Rotating image to portrait orientation');
        processedImage = img.copyRotate(image, angle: 90);
        imageModified = true;
      }
      
      // Apply different, but minimal, processing for each platform when face detection is needed
      if (isForFaceDetection) {
        if (Platform.isIOS) {
          // iOS cameras sometimes need minimal adjustments for face detection to work
          // These are very subtle adjustments that shouldn't visibly change the image
          processedImage = img.adjustColor(
            processedImage,
            contrast: 1.08,  // Barely noticeable contrast
            exposure: 0.03   // Very minimal exposure adjustment
          );
          imageModified = true;
        } else {
          // For Android, resize if the image is very large
          // This helps with face detection without changing the appearance
          if (image.width > 2000 || image.height > 2000) {
            // Scale down very large images to improve face detection without changing appearance
            double scale = min(2000 / image.width, 2000 / image.height);
            processedImage = img.copyResize(
              processedImage,
              width: (image.width * scale).round(),
              height: (image.height * scale).round(),
              interpolation: img.Interpolation.linear
            );
            imageModified = true;
          }
        }
      }
      
      // Save to temporary file only if any processing was applied
      if (imageModified) {
        final tempDir = await getTemporaryDirectory();
        final tempFile = File('${tempDir.path}/processed_${DateTime.now().millisecondsSinceEpoch}.jpg');
        await tempFile.writeAsBytes(img.encodeJpg(processedImage, quality: 100));
        
        debugPrint('$TAG: Saved processed image: ${tempFile.path}');
        return tempFile;
      } else {
        // Return the original file if no changes were needed
        return imageFile;
      }
    } catch (e) {
      debugPrint('$TAG: Error during preprocessing: $e');
      return imageFile; // Return original if processing fails
    }
  }
}