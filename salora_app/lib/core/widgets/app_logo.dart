import 'dart:math' as math;

import 'package:flutter/material.dart';
import '../constants/app_constants.dart';
import '../theme/app_colors.dart';

class AppLogo extends StatelessWidget {
  final double size;
  final bool withText;
  final CrossAxisAlignment alignment;

  const AppLogo({
    super.key,
    this.size = 72,
    this.withText = true,
    this.alignment = CrossAxisAlignment.center,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: alignment,
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: size,
          height: size,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(size * .30),
            gradient: const LinearGradient(
              colors: [Color(0xFF2563EB), Color(0xFF7C3AED), Color(0xFFF59E0B)],
              begin: Alignment.topRight,
              end: Alignment.bottomLeft,
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withOpacity(.36),
                blurRadius: 28,
                offset: const Offset(0, 14),
              ),
            ],
          ),
          child: CustomPaint(painter: _SaloraLogoPainter()),
        ),
        if (withText) ...[
          const SizedBox(height: 12),
          const Text(
            AppConstants.appName,
            style: TextStyle(fontSize: 31, fontWeight: FontWeight.w900, letterSpacing: -.6),
          ),
          const SizedBox(height: 4),
          const Text(
            'خطط • احجز • احتفل',
            style: TextStyle(color: AppColors.textSecondary, fontWeight: FontWeight.w700),
          ),
        ],
      ],
    );
  }
}

class _SaloraLogoPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final white = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    final fill = Paint()..color = Colors.white.withOpacity(.96);
    final w = size.width;
    final h = size.height;

    white.strokeWidth = w * .065;
    final arch = Path()
      ..moveTo(w * .22, h * .68)
      ..quadraticBezierTo(w * .50, h * .24, w * .78, h * .68)
      ..lineTo(w * .78, h * .78)
      ..moveTo(w * .22, h * .68)
      ..lineTo(w * .22, h * .78);
    canvas.drawPath(arch, white);

    white.strokeWidth = w * .045;
    canvas.drawLine(Offset(w * .33, h * .66), Offset(w * .33, h * .79), white);
    canvas.drawLine(Offset(w * .67, h * .66), Offset(w * .67, h * .79), white);
    canvas.drawLine(Offset(w * .18, h * .80), Offset(w * .82, h * .80), white);

    final sStyle = TextStyle(
      color: Colors.white,
      fontSize: w * .40,
      fontWeight: FontWeight.w900,
      height: 1,
    );
    final tp = TextPainter(
      text: TextSpan(text: 'S', style: sStyle),
      textAlign: TextAlign.center,
      textDirection: TextDirection.ltr,
    )..layout();
    tp.paint(canvas, Offset((w - tp.width) / 2, h * .33));

    final star = Path();
    final center = Offset(w * .73, h * .27);
    final r = w * .075;
    for (var i = 0; i < 8; i++) {
      final angle = -1.5708 + i * .7854;
      final rr = i.isEven ? r : r * .42;
      final p = Offset(center.dx + rr * math.cos(angle), center.dy + rr * math.sin(angle));
      if (i == 0) star.moveTo(p.dx, p.dy); else star.lineTo(p.dx, p.dy);
    }
    star.close();
    canvas.drawPath(star, fill);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
