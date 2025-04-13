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
      await tempFile.writeAsBytes(img.encodeJpg(croppedImage, quality: 95));
      
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
      await tempFile.writeAsBytes(img.encodeJpg(resizedImage, quality: 90));
      
      return tempFile;
    } catch (e) {
      debugPrint('$TAG: Error resizing image: $e');
      return null;
    }
  }
  
  // Custom sharpen function using a simple approach
  static img.Image sharpenImage(img.Image src, {double amount = 1.0}) {
    // Instead of pixel-by-pixel manipulation, just use built-in adjustments
    // Increase contrast as a form of sharpening
    var enhanced = img.adjustColor(
      src,
      contrast: 1.0 + amount * 0.3  // Increase contrast based on amount
    );
    
    return enhanced;
  }
  
  // Enhance image for better face detection
  // Add this method to your ImageUtils class
  static Future<File?> enhanceImageForFaceDetection(File imageFile) async {
    try {
      debugPrint('$TAG: Enhancing image for face detection on ${Platform.isIOS ? "iOS" : "Android"}: ${imageFile.path}');
      
      final bytes = await imageFile.readAsBytes();
      final image = img.decodeImage(bytes);
      
      if (image == null) {
        debugPrint('$TAG: Failed to decode image');
        return null;
      }
      
      // Different enhancement parameters based on platform
      var enhancedImage = img.adjustColor(
        image,
        //contrast: Platform.isIOS ? 1.4 : 1.1,         // Much higher contrast for iOS
        brightness: Platform.isIOS ? 0.2 : 1.05,      // Much brighter for iOS
        //saturation: Platform.isIOS ? 1.3 : 1.0,       // More saturated for iOS
        //exposure: Platform.isIOS ? 0.15 : 0.0         // Add exposure for iOS
      );
      
      // Apply custom sharpening more aggressively on iOS
      enhancedImage = sharpenImage(enhancedImage, amount: Platform.isIOS ? 0.8 : 0.5);
      
      // Save to temporary file
      final tempDir = await getTemporaryDirectory();
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      final outputPath = '${tempDir.path}/enhanced_${timestamp}.jpg';
      
      // Use higher quality for iOS to preserve details
      final quality = Platform.isIOS ? 95 : 90;
      final enhancedJpg = img.encodeJpg(enhancedImage, quality: quality);
      
      final outputFile = File(outputPath);
      await outputFile.writeAsBytes(enhancedJpg);
      
      debugPrint('$TAG: Enhanced image saved to: ${outputFile.path}');
      return outputFile;
    } catch (e) {
      debugPrint('$TAG: Error enhancing image: $e');
      return null;
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
  
  // Platform-specific image preprocessing
  static Future<File?> preprocessImageForPlatform(File imageFile, {bool isForFaceDetection = true}) async {
    if (Platform.isIOS) {
      // iOS needs different processing parameters
      try {
        final bytes = await imageFile.readAsBytes();
        final image = img.decodeImage(bytes);
        
        if (image == null) {
          debugPrint('$TAG: Failed to decode iOS image');
          return imageFile; // Return original if processing fails
        }
        
        var processedImage = image;
        
        // Check orientation and rotate if needed (iOS often captures in landscape)
        bool needsRotation = image.width > image.height;
        
        if (needsRotation) {
          debugPrint('$TAG: Rotating iOS image to portrait orientation');
          processedImage = img.copyRotate(image, angle: 90);
        }
        
        // iOS camera often produces darker images
        if (isForFaceDetection) {
          // Brighter and more contrast for face detection
          processedImage = img.adjustColor(
            processedImage,
            brightness: 1.15,
            contrast: 1.2,
            saturation: 1.0
          );
          
          // Add sharpening specifically for face detection
          processedImage = sharpenImage(processedImage, amount: 0.6);
        } else {
          // Subtle enhancement for general use
          processedImage = img.adjustColor(
            processedImage,
            brightness: 1.05,
            contrast: 1.1
          );
        }
        
        // Save to temporary file
        final tempDir = await getTemporaryDirectory();
        final tempFile = File('${tempDir.path}/ios_processed_${DateTime.now().millisecondsSinceEpoch}.jpg');
        await tempFile.writeAsBytes(img.encodeJpg(processedImage, quality: 95));
        
        debugPrint('$TAG: Saved iOS processed image: ${tempFile.path}');
        return tempFile;
      } catch (e) {
        debugPrint('$TAG: Error processing iOS image: $e');
        return imageFile; // Return original if processing fails
      }
    } else {
      // Android processing
      if (isForFaceDetection) {
        return enhanceImageForFaceDetection(imageFile);
      }
      return imageFile; // Android images are generally good as-is
    }
  }
}