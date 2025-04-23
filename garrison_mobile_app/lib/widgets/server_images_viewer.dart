// Create a new file: lib/widgets/server_images_viewer.dart

import 'dart:io';
import 'package:flutter/material.dart';

/// A widget to display server face images
class ServerImagesViewer extends StatefulWidget {
  final List<String> imagePaths;
  final Function()? onClose;
  
  const ServerImagesViewer({
    Key? key,
    required this.imagePaths,
    this.onClose,
  }) : super(key: key);

  @override
  State<ServerImagesViewer> createState() => _ServerImagesViewerState();
}

class _ServerImagesViewerState extends State<ServerImagesViewer> {
  int _currentIndex = 0;
  
  @override
  Widget build(BuildContext context) {
    if (widget.imagePaths.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('No server images available'),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: widget.onClose,
              child: const Text('Close'),
            ),
          ],
        ),
      );
    }
    
    return Container(
      color: Colors.black.withOpacity(0.9),
      child: SafeArea(
        child: Column(
          children: [
            // Header
            Padding(
              padding: const EdgeInsets.all(8.0),
              child: Row(
                children: [
                  Text(
                    'Server Face Images (${_currentIndex + 1}/${widget.imagePaths.length})',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const Spacer(),
                  IconButton(
                    icon: const Icon(Icons.close, color: Colors.white),
                    onPressed: widget.onClose,
                  ),
                ],
              ),
            ),
            
            // Image display
            Expanded(
              child: GestureDetector(
                onTap: () {
                  setState(() {
                    _currentIndex = (_currentIndex + 1) % widget.imagePaths.length;
                  });
                },
                child: Container(
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.blueAccent, width: 2),
                  ),
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      // Main image
                      Image.file(
                        File(widget.imagePaths[_currentIndex]),
                        fit: BoxFit.contain,
                      ),
                      
                      // File info overlay
                      Positioned(
                        bottom: 0,
                        left: 0,
                        right: 0,
                        child: Container(
                          color: Colors.black.withOpacity(0.7),
                          padding: const EdgeInsets.all(8),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'File: ${widget.imagePaths[_currentIndex].split('/').last}',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 12,
                                ),
                              ),
                              Text(
                                'Path: ${widget.imagePaths[_currentIndex]}',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 10,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                              Text(
                                'Tap to cycle through ${widget.imagePaths.length} images',
                                style: const TextStyle(
                                  color: Colors.lightBlueAccent,
                                  fontSize: 12,
                                  fontStyle: FontStyle.italic,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            
            // Controls
            Padding(
              padding: const EdgeInsets.all(8.0),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  ElevatedButton.icon(
                    icon: const Icon(Icons.arrow_back),
                    label: const Text('Previous'),
                    onPressed: () {
                      setState(() {
                        _currentIndex = (_currentIndex - 1 + widget.imagePaths.length) % widget.imagePaths.length;
                      });
                    },
                  ),
                  const SizedBox(width: 16),
                  ElevatedButton.icon(
                    icon: const Icon(Icons.arrow_forward),
                    label: const Text('Next'),
                    onPressed: () {
                      setState(() {
                        _currentIndex = (_currentIndex + 1) % widget.imagePaths.length;
                      });
                    },
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}