import 'package:flutter/material.dart';
import '../core/theme/app_colors.dart';

class EmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final String? buttonText;
  final VoidCallback? onPressed;

  const EmptyState({super.key, required this.icon, required this.title, required this.subtitle, this.buttonText, this.onPressed});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(26),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          CircleAvatar(radius: 42, backgroundColor: AppColors.surface, child: Icon(icon, size: 42, color: AppColors.textSecondary)),
          const SizedBox(height: 18),
          Text(title, textAlign: TextAlign.center, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          Text(subtitle, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.textSecondary)),
          if (buttonText != null && onPressed != null) ...[
            const SizedBox(height: 20),
            ElevatedButton(onPressed: onPressed, child: Text(buttonText!)),
          ],
        ]),
      ),
    );
  }
}
