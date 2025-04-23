// lib/util/image_utils.dart
import 'dart:io';
import 'dart:math' as math;
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

  /// Special iOS-specific method for safer image enhancement without overprocessing
  static Future<File?> safeEnhanceImage(File imageFile) async {
    try {
      debugPrint('$TAG: Performing safe enhancement for iOS image: ${imageFile.path}');
      
      // Check if image is dark
      final bool isDark = await _isImageDark(imageFile);
      
      if (!isDark) {
        debugPrint('$TAG: Image doesn\'t need enhancement');
        return imageFile;
      }
      
      // Apply brightness enhancement for dark images
      final bytes = await imageFile.readAsBytes();
      final image = img.decodeImage(bytes);
      
      if (image == null) {
        debugPrint('$TAG: Failed to decode image');
        return imageFile;
      }
      
      final enhancedImage = img.adjustColor(
        image,
        brightness: 0.3,
        contrast: 1.1,
      );
      
      // Save to temporary file
      final tempDir = await getTemporaryDirectory();
      final tempFile = File('${tempDir.path}/enhanced_${DateTime.now().millisecondsSinceEpoch}.jpg');
      await tempFile.writeAsBytes(img.encodeJpg(enhancedImage, quality: 95));
      
      return tempFile;
    } catch (e) {
      debugPrint('$TAG: Safe enhance error: $e');
      return imageFile;
    }
  }

  /// Apply a series of iOS-specific enhancements for face detection
  static Future<File?> enhanceForIOSFaceDetection(File imageFile) async {
    try {
      debugPrint('$TAG: Applying specialized iOS enhancement for face detection');
      
      // Read the image
      final bytes = await imageFile.readAsBytes();
      final image = img.decodeImage(bytes);
      
      if (image == null) {
        debugPrint('$TAG: Failed to decode image for iOS enhancement');
        return null;
      }
      
      // 1. Ensure proper size for iOS face detection
      img.Image processedImage = image;
      final int maxWidth = 1200;  // iOS face detection works best around this size
      
      if (image.width > maxWidth) {
        final int targetHeight = (maxWidth * image.height ~/ image.width);
        processedImage = img.copyResize(
          processedImage,
          width: maxWidth,
          height: targetHeight,
          interpolation: img.Interpolation.cubic
        );
      }
      
      // 2. Apply iOS-optimized enhancement settings
      processedImage = img.adjustColor(
        processedImage,
        brightness: 0.15,     // Slightly brighter
        contrast: 1.25,       // More contrast
        saturation: 1.15,     // Slightly more saturated to enhance skin tones
        exposure: 0.1,        // Slightly better exposure
      );
      
      // 3. Apply gentle noise reduction (using blur instead of undefined sharpen)
      processedImage = img.gaussianBlur(processedImage, radius: 1);
      
      // Save to high-quality JPEG
      final tempDir = await getTemporaryDirectory();
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      final outputPath = '${tempDir.path}/ios_enhanced_$timestamp.jpg';
      
      final outputFile = File(outputPath);
      await outputFile.writeAsBytes(img.encodeJpg(processedImage, quality: 92));
      
      debugPrint('$TAG: iOS enhancement complete: $outputPath');
      return outputFile;
    } catch (e) {
      debugPrint('$TAG: Error enhancing image for iOS: $e');
      return null;
    }
  }

  /// Create multiple versions of an image with different processing settings
  /// to maximize the chance of successful face detection on iOS
  static Future<List<File>> createIOSFaceDetectionVariants(File imageFile) async {
    try {
      final List<File> variants = [];
      
      // Add the original
      variants.add(imageFile);
      
      // Try standard enhancement
      final File? enhanced = await enhanceForIOSFaceDetection(imageFile);
      if (enhanced != null) {
        variants.add(enhanced);
      }
      
      // Try multiple orientations
      for (final angle in [90, 270]) {
        try {
          final File? rotated = await rotateImage(imageFile, angle);
          if (rotated != null) {
            variants.add(rotated);
            
            // Also add enhanced versions of rotated images
            final File? enhancedRotated = await enhanceForIOSFaceDetection(rotated);
            if (enhancedRotated != null) {
              variants.add(enhancedRotated);
            }
          }
        } catch (e) {
          debugPrint('$TAG: Error creating rotated variant: $e');
        }
      }
      
      // Try different enhancement settings
      final bytes = await imageFile.readAsBytes();
      final originalImage = img.decodeImage(bytes);
      
      if (originalImage != null) {
        final tempDir = await getTemporaryDirectory();
        final timestamp = DateTime.now().millisecondsSinceEpoch;
        
        // Try various enhancement combinations
        final enhancementSettings = [
          {'brightness': 0.3, 'contrast': 1.4, 'description': 'high_bright_contrast'},
          {'brightness': 0.1, 'contrast': 1.5, 'description': 'high_contrast'},
          {'brightness': 0.4, 'contrast': 1.1, 'description': 'very_bright'},
        ];
        
        for (final settings in enhancementSettings) {
          try {
            final processed = img.adjustColor(
              originalImage,
              brightness: (settings['brightness'] as num).toDouble(),
              contrast: (settings['contrast'] as num).toDouble(),
            );
            
            final outputPath = '${tempDir.path}/ios_${settings['description']}_$timestamp.jpg';
            final outputFile = File(outputPath);
            await outputFile.writeAsBytes(img.encodeJpg(processed, quality: 90));
            variants.add(outputFile);
          } catch (e) {
            debugPrint('$TAG: Error creating enhanced variant: $e');
          }
        }
      }
      
      debugPrint('$TAG: Created ${variants.length} face detection variants for iOS');
      return variants;
    } catch (e) {
      debugPrint('$TAG: Error creating iOS variants: $e');
      return [imageFile]; // Return only original on error
    }
  }
  
  /// Rotate an image by the specified angle
  static Future<File?> rotateImage(File imageFile, int angle) async {
    try {
      final bytes = await imageFile.readAsBytes();
      final image = img.decodeImage(bytes);
      
      if (image == null) {
        return null;
      }
      
      final rotated = img.copyRotate(image, angle: angle);
      
      final tempDir = await getTemporaryDirectory();
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      final outputPath = '${tempDir.path}/rotated_${angle}_$timestamp.jpg';
      
      final outputFile = File(outputPath);
      await outputFile.writeAsBytes(img.encodeJpg(rotated, quality: 90));
      
      return outputFile;
    } catch (e) {
      debugPrint('$TAG: Error rotating image: $e');
      return null;
    }
  }
  
  /// Enhance and crop to focus on the detected face region
  static Future<File?> processFaceRegion(File imageFile, Rect faceBounds) async {
    try {
      final bytes = await imageFile.readAsBytes();
      final image = img.decodeImage(bytes);
      
      if (image == null) {
        return null;
      }
      
      // Add margin around the face (25% on each side)
      final int centerX = (faceBounds.left + faceBounds.right).toInt() ~/ 2;
      final int centerY = (faceBounds.top + faceBounds.bottom).toInt() ~/ 2;
      final int faceWidth = faceBounds.width.toInt();
      final int faceHeight = faceBounds.height.toInt();
      
      // Calculate crop region with margins
      final int margin = (faceWidth * 0.3).toInt(); // 30% margin
      final int cropLeft = math.max(0, centerX - faceWidth ~/ 2 - margin);
      final int cropTop = math.max(0, centerY - faceHeight ~/ 2 - margin);
      final int cropWidth = math.min(image.width - cropLeft, faceWidth + margin * 2);
      final int cropHeight = math.min(image.height - cropTop, faceHeight + margin * 2);
      
      // Crop the face region with margin
      final croppedImage = img.copyCrop(
        image,
        x: cropLeft,
        y: cropTop,
        width: cropWidth,
        height: cropHeight,
      );
      
      // Apply enhancement
      final enhancedImage = img.adjustColor(
        croppedImage,
        brightness: 0.1,
        contrast: 1.2,
        saturation: 1.1,
      );
      
      // Save to file
      final tempDir = await getTemporaryDirectory();
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      final outputPath = '${tempDir.path}/face_region_$timestamp.jpg';
      
      final outputFile = File(outputPath);
      await outputFile.writeAsBytes(img.encodeJpg(enhancedImage, quality: 92));
      
      return outputFile;
    } catch (e) {
      debugPrint('$TAG: Error processing face region: $e');
      return null;
    }
  }
  
  /// Specifically optimized for face detection on problematic iOS devices
  static Future<List<File>> createOptimizedFaceDetectionVariants(File imageFile) async {
    try {
      final List<File> variants = [];
      
      // Add the original file
      variants.add(imageFile);
      
      // Basic enhanced version
      final File? enhanced = await enhanceImageForFaceDetection(imageFile);
      if (enhanced != null) {
        variants.add(enhanced);
      }
      
      // Read the image
      final bytes = await imageFile.readAsBytes();
      final originalImage = img.decodeImage(bytes);
      
      if (originalImage == null) {
        return variants;
      }
      
      final tempDir = await getTemporaryDirectory();
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      
      // Try with several brightness and contrast combinations
      final List<Map<String, double>> enhancements = [
        {'brightness': 0.2, 'contrast': 1.3},  // Brighter with more contrast
        {'brightness': 0.3, 'contrast': 1.1},  // Very bright, less contrast
        {'brightness': 0.0, 'contrast': 1.5},  // High contrast only
        {'brightness': -0.1, 'contrast': 1.3}, // Slightly darker with contrast
      ];
      
      for (final settings in enhancements) {
        try {
          final variant = img.adjustColor(
            originalImage,
            brightness: settings['brightness']!,
            contrast: settings['contrast']!,
          );
          
          final String adjustmentDesc = 'br${settings['brightness']!}_cn${settings['contrast']!}';
          final outputPath = '${tempDir.path}/face_${adjustmentDesc}_$timestamp.jpg';
          final outputFile = File(outputPath);
          await outputFile.writeAsBytes(img.encodeJpg(variant, quality: 95));
          variants.add(outputFile);
        } catch (e) {
          debugPrint('$TAG: Error creating variant: $e');
        }
      }
      
      // Try multiple image sizes
      final List<int> sizeVariants = [800, 640, 480];
      for (final size in sizeVariants) {
        try {
          // Compute scaled dimensions
          final double aspectRatio = originalImage.width / originalImage.height;
          final int width = size;
          final int height = (width / aspectRatio).round();
          
          final resized = img.copyResize(
            originalImage,
            width: width,
            height: height,
          );
          
          final outputPath = '${tempDir.path}/face_sized_${width}x${height}_$timestamp.jpg';
          final outputFile = File(outputPath);
          await outputFile.writeAsBytes(img.encodeJpg(resized, quality: 90));
          variants.add(outputFile);
          
          // Also add an enhanced version of this size
          final enhancedResized = img.adjustColor(
            resized,
            brightness: 0.2,
            contrast: 1.3,
          );
          
          final enhancedOutputPath = '${tempDir.path}/face_enhanced_${width}x${height}_$timestamp.jpg';
          final enhancedOutputFile = File(enhancedOutputPath);
          await enhancedOutputFile.writeAsBytes(img.encodeJpg(enhancedResized, quality: 90));
          variants.add(enhancedOutputFile);
        } catch (e) {
          debugPrint('$TAG: Error creating resized variant: $e');
        }
      }
      
      debugPrint('$TAG: Created ${variants.length} image variants for iOS face detection');
      return variants;
    } catch (e) {
      debugPrint('$TAG: Error creating face detection variants: $e');
      return [imageFile]; // Return only original on error
    }
  }

  // Enhanced iOS-specific preprocessing for face detection
  static Future<File?> iOSOptimizedPreprocessing(File imageFile) async {
    try {
      debugPrint('$TAG: Applying iOS-optimized preprocessing to: ${imageFile.path}');
      
      // Read the image
      final bytes = await imageFile.readAsBytes();
      final image = img.decodeImage(bytes);
      
      if (image == null) {
        debugPrint('$TAG: Failed to decode image for iOS preprocessing');
        return null;
      }
      
      // Create output file path
      final tempDir = await getTemporaryDirectory();
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      final outputPath = '${tempDir.path}/ios_optimized_$timestamp.jpg';
      
      // Step 1: Check if we need to rotate the image for proper orientation
      // Most phone cameras capture in landscape but UI expects portrait
      img.Image processedImage = image;
      if (image.width > image.height) {
        debugPrint('$TAG: Rotating image to portrait orientation');
        processedImage = img.copyRotate(image, angle: 90);
      }
      
      // Step 2: Resize to an optimal size for face detection
      // ML Kit works better with images around 800-1000px on the longer side
      const int targetMaxDimension = 800;
      if (processedImage.width > targetMaxDimension || processedImage.height > targetMaxDimension) {
        final int targetWidth, targetHeight;
        if (processedImage.width > processedImage.height) {
          targetWidth = targetMaxDimension;
          targetHeight = (targetMaxDimension * processedImage.height / processedImage.width).round();
        } else {
          targetHeight = targetMaxDimension;
          targetWidth = (targetMaxDimension * processedImage.width / processedImage.height).round();
        }
        
        processedImage = img.copyResize(
          processedImage,
          width: targetWidth,
          height: targetHeight,
          interpolation: img.Interpolation.cubic
        );
        
        debugPrint('$TAG: Resized image to ${targetWidth}x${targetHeight}');
      }
      
      // Step 3: Apply multiple enhancement approaches and save each version
      List<File> variants = [];
      
      // Base variant - save resized image without enhancements
      final baseImageFile = File(outputPath);
      await baseImageFile.writeAsBytes(img.encodeJpg(processedImage, quality: 95));
      variants.add(baseImageFile);
      
      // Variant 1: Brightness and contrast boost
      try {
        final brightImage = img.adjustColor(
          processedImage,
          brightness: 0.15,
          contrast: 1.2,
          saturation: 1.1,
          exposure: 0.1
        );
        
        final brightPath = '${tempDir.path}/ios_bright_$timestamp.jpg';
        final brightFile = File(brightPath);
        await brightFile.writeAsBytes(img.encodeJpg(brightImage, quality: 90));
        variants.add(brightFile);
      } catch (e) {
        debugPrint('$TAG: Error creating brightness variant: $e');
      }
      
      // Variant 2: High contrast
      try {
        final contrastImage = img.adjustColor(
          processedImage,
          brightness: 0.05,
          contrast: 1.4,
          saturation: 1.0
        );
        
        final contrastPath = '${tempDir.path}/ios_contrast_$timestamp.jpg';
        final contrastFile = File(contrastPath);
        await contrastFile.writeAsBytes(img.encodeJpg(contrastImage, quality: 90));
        variants.add(contrastFile);
      } catch (e) {
        debugPrint('$TAG: Error creating contrast variant: $e');
      }
      
      // Variant 3: Higher contrast image (instead of sharpening)
      try {
        // Use a high contrast adjustment instead
        final contrastImage2 = img.adjustColor(
          processedImage,
          brightness: 0,
          contrast: 1.8,  // Very high contrast
          saturation: 1.2
        );
        
        final contrastPath2 = '${tempDir.path}/ios_highcontrast_$timestamp.jpg';
        final contrastFile2 = File(contrastPath2);
        await contrastFile2.writeAsBytes(img.encodeJpg(contrastImage2, quality: 90));
        variants.add(contrastFile2);
      } catch (e) {
        debugPrint('$TAG: Error creating high contrast variant: $e');
      }
      
      // Step 4: Test each variant with face detection to find the best one
      FaceDetector detector = FaceDetector(
        options: FaceDetectorOptions(
          enableLandmarks: true,
          enableClassification: true,
          enableTracking: true,
          minFaceSize: 0.05,
          performanceMode: FaceDetectorMode.accurate,
        ),
      );
      
      File bestVariant = variants.first;
      int maxFaceCount = 0;
      
      for (final variant in variants) {
        try {
          final inputImage = InputImage.fromFilePath(variant.path);
          final faces = await detector.processImage(inputImage);
          
          debugPrint('$TAG: Variant ${variant.path} detected ${faces.length} faces');
          
          if (faces.length > maxFaceCount) {
            maxFaceCount = faces.length;
            bestVariant = variant;
            
            // If we found a face, we can stop testing
            if (maxFaceCount > 0) break;
          }
        } catch (e) {
          debugPrint('$TAG: Error testing variant ${variant.path}: $e');
        }
      }
      
      // Clean up unused variants
      for (final variant in variants) {
        if (variant.path != bestVariant.path) {
          try {
            await variant.delete();
          } catch (e) {
            // Ignore deletion errors
          }
        }
      }
      
      // Close the detector
      detector.close();
      
      debugPrint('$TAG: Best variant: ${bestVariant.path} with $maxFaceCount faces');
      return bestVariant;
    } catch (e) {
      debugPrint('$TAG: Error in iOS optimized preprocessing: $e');
      return null;
    }
  }
  
  // Enhance image for face detection - with minimal but necessary processing
  static Future<File?> enhanceImageForFaceDetection(File imageFile) async {
    try {
      debugPrint('$TAG: Processing image for face detection with improved algorithm: ${imageFile.path}');
      
      final bytes = await imageFile.readAsBytes();
      final image = img.decodeImage(bytes);
      
      if (image == null) {
        debugPrint('$TAG: Failed to decode image');
        return null;
      }
      
      var processedImage = image;
      
      // For iOS, apply more moderate enhancements to help with face detection
      // without making the image too dark
      if (Platform.isIOS) {
        processedImage = img.adjustColor(
          image,
          brightness: 0.05,
          contrast: 1.1,
          saturation: 1.05,
          exposure: 0.05
        );
        
        // Check if image is too dark
        bool isDark = await _isImageDark(imageFile);
        if (isDark) {
          debugPrint('$TAG: Image appears dark, applying MORE brightness');
          // Use positive brightness values for dark images
          processedImage = img.adjustColor(
            image,
            brightness: 0.3,
            contrast: 0.9,
            exposure: 0.2
          );
        }
      }
      
      // Save to temporary file with higher quality
      final tempDir = await getTemporaryDirectory();
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      final outputPath = '${tempDir.path}/enhanced_face_${timestamp}.jpg';
      
      // Use maximum quality to preserve details and prevent black images
      final processedJpg = img.encodeJpg(processedImage, quality: 98);
      
      final outputFile = File(outputPath);
      await outputFile.writeAsBytes(processedJpg);
      
      // Verify file size - if too small, image might be too dark or corrupt
      final fileSize = await outputFile.length();
      debugPrint('$TAG: Enhanced image saved to: ${outputFile.path}, size: $fileSize bytes');
      
      // If the file is suspiciously small, it might be too dark - use original instead
      if (fileSize < 20000) {
        debugPrint('$TAG: Enhanced image is suspiciously small, using original instead');
        return imageFile;
      }
      
      return outputFile;
    } catch (e) {
      debugPrint('$TAG: Error enhancing image: $e');
      return imageFile; // Return original on error
    }
  }
  
  // Helper method to check if an image is too dark
  static Future<bool> _isImageDark(File imageFile) async {
    try {
      final bytes = await imageFile.readAsBytes();
      final image = img.decodeImage(bytes);
      
      if (image == null) {
        return false;
      }
      
      return _isImageDarkInternal(image);
    } catch (e) {
      debugPrint('$TAG: Error checking image darkness: $e');
      return false;
    }
  }

  static bool _isImageDarkInternal(img.Image image) {
    try {
      // Calculate average brightness
      int totalBrightness = 0;
      int pixelCount = 0;
      
      // Sample the image (checking every 10th pixel to save processing time)
      for (int y = 0; y < image.height; y += 10) {
        for (int x = 0; x < image.width; x += 10) {
          final pixel = image.getPixel(x, y);
          
          // Access r, g, b properties directly from the Pixel object
          final brightness = (0.299 * pixel.r + 0.587 * pixel.g + 0.114 * pixel.b).round();
          totalBrightness += brightness;
          pixelCount++;
        }
      }
      
      if (pixelCount == 0) return false;
      
      final averageBrightness = totalBrightness / pixelCount;
      // Increase threshold to catch more "dark" images
      final isDark = averageBrightness < 100; // Was 80, increased to 100
      
      debugPrint('$TAG: Image average brightness: $averageBrightness, isDark: $isDark');
      return isDark;
    } catch (e) {
      debugPrint('$TAG: Error analyzing image brightness: $e');
      return false; // Default to not dark if analysis fails
    }
  }

  // Create multi-orientation face detection - create multiple versions of an image with different orientations
  static Future<List<File>> createMultiOrientationImages(File imageFile) async {
    final List<File> results = [];
    
    try {
      debugPrint('$TAG: Creating multi-orientation images for iOS face detection');
      
      // First add the original
      results.add(imageFile);
      
      // Add enhanced version
      final enhanced = await enhanceImageForFaceDetection(imageFile);
      if (enhanced != null) {
        results.add(enhanced);
      }
      
      // Create rotated versions
      for (final angle in [90, 270, 180]) {
        try {
          final bytes = await imageFile.readAsBytes();
          final image = img.decodeImage(bytes);
          
          if (image == null) {
            debugPrint('$TAG: Failed to decode image for rotation');
            continue;
          }
          
          final rotated = img.copyRotate(image, angle: angle);
          
          // Save to temporary file
          final tempDir = await getTemporaryDirectory();
          final timestamp = DateTime.now().millisecondsSinceEpoch;
          final outputPath = '${tempDir.path}/rotated_${angle}_$timestamp.jpg';
          
          final outputFile = File(outputPath);
          await outputFile.writeAsBytes(img.encodeJpg(rotated, quality: 90));
          
          debugPrint('$TAG: Created rotated image ($angle°): ${outputPath}');
          results.add(outputFile);
          
          // Also add enhanced version of the rotated image
          final enhancedRotated = img.adjustColor(
            rotated,
            brightness: 0.1,
            contrast: 1.2,
            saturation: 1.1
          );
          
          final enhancedRotatedPath = '${tempDir.path}/rotated_enhanced_${angle}_$timestamp.jpg';
          final enhancedRotatedFile = File(enhancedRotatedPath);
          await enhancedRotatedFile.writeAsBytes(img.encodeJpg(enhancedRotated, quality: 90));
          
          results.add(enhancedRotatedFile);
        } catch (e) {
          debugPrint('$TAG: Error creating rotated image $angle°: $e');
        }
      }
      
      debugPrint('$TAG: Created ${results.length} orientation variants');
      return results;
    } catch (e) {
      debugPrint('$TAG: Error creating multi-orientation images: $e');
      // Return at least the original
      return [imageFile];
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