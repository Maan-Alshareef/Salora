import 'dart:io';

import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

class UserAvatar extends StatelessWidget {
  const UserAvatar({
    super.key,
    this.imageUrl,
    this.localFile,
    this.radius = 36,
    this.heroTag,
  });

  final String? imageUrl;
  final File? localFile;
  final double radius;
  final Object? heroTag;

  @override
  Widget build(BuildContext context) {
    final avatar = Container(
      width: radius * 2,
      height: radius * 2,
      decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.primary),
      clipBehavior: Clip.antiAlias,
      child: _image(),
    );
    return heroTag == null ? avatar : Hero(tag: heroTag!, child: avatar);
  }

  Widget _image() {
    if (localFile != null) {
      return Image.file(
        localFile!,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) => _fallback(),
      );
    }
    final url = imageUrl?.trim();
    if (url != null && url.isNotEmpty) {
      return Image.network(
        url,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) => _fallback(),
      );
    }
    return _fallback();
  }

  Widget _fallback() => Icon(Icons.person_rounded, color: Colors.white, size: radius);
}
