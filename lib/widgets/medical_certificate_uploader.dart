import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:mime/mime.dart';

class MedicalCertificateUploader extends StatefulWidget {
  final Function(File?) onFileSelected;
  
  const MedicalCertificateUploader({
    Key? key,
    required this.onFileSelected,
  }) : super(key: key);

  @override
  State<MedicalCertificateUploader> createState() => _MedicalCertificateUploaderState();
}

class _MedicalCertificateUploaderState extends State<MedicalCertificateUploader> {
  File? _selectedFile;
  final ImagePicker _picker = ImagePicker();

  Future<void> _pickImage(ImageSource source) async {
  try {
    final XFile? pickedFile = await _picker.pickImage(
      source: source,
      maxWidth: 1800,
      maxHeight: 1800,
      imageQuality: 85,
    );
    
    if (pickedFile != null) {
      // Validate file type
      final extension = pickedFile.path.split('.').last.toLowerCase();
      final allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

      if (allowedExtensions.contains(extension)) {
        final file = File(pickedFile.path);
        
        // Additional mime type check
        final mimeType = lookupMimeType(file.path);
        print('Detected MIME type: $mimeType');

        setState(() {
          _selectedFile = file;
        });
        widget.onFileSelected(_selectedFile);
      } else {
        _showErrorDialog('Invalid file type. Please upload JPG, PNG, or PDF');
      }
    }
  } catch (e) {
    print('Detailed error picking image: $e');
    _showErrorDialog('Error picking image: $e');
  }
}

  void _showErrorDialog(String message) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Upload Error'),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  void _showImageSourceDialog() {
  showDialog(
    context: context,
    builder: (context) => AlertDialog(
      title: const Text('Select Medical Certificate'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('Allowed file types: JPG, PNG, PDF (max 5MB)'),
          const SizedBox(height: 16),
          ListTile(
            leading: const Icon(Icons.photo_camera),
            title: const Text('Take Photo (JPG/PNG)'),
            onTap: () {
              Navigator.pop(context);
              _pickImage(ImageSource.camera);
            },
          ),
          ListTile(
            leading: const Icon(Icons.photo_library),
            title: const Text('Choose from Gallery (JPG/PNG/PDF)'),
            onTap: () {
              Navigator.pop(context);
              _pickImage(ImageSource.gallery);
            },
          ),
        ],
      ),
    ),
  );
}

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (_selectedFile != null) ...[
          Container(
            height: 200,
            decoration: BoxDecoration(
              border: Border.all(color: Colors.grey),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Stack(
              fit: StackFit.expand,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.file(
                    _selectedFile!,
                    fit: BoxFit.cover,
                    errorBuilder: (context, error, stackTrace) {
                      print('Image display error: $error'); // Added error logging
                      return Center(
                        child: Text('Error loading image: $error'),
                      );
                    },
                  ),
                ),
                Positioned(
                  top: 8,
                  right: 8,
                  child: IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () {
                      setState(() {
                        _selectedFile = null;
                      });
                      widget.onFileSelected(null);
                    },
                    style: IconButton.styleFrom(
                      backgroundColor: Colors.white.withOpacity(0.8),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
        ],
        OutlinedButton.icon(
          onPressed: _showImageSourceDialog,
          icon: const Icon(Icons.upload_file),
          label: Text(_selectedFile == null 
            ? 'Upload Medical Certificate' 
            : 'Change Medical Certificate'),
        ),
      ],
    );
  }
}