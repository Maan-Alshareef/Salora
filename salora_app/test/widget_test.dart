import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:salora_app/core/widgets/app_logo.dart';

void main() {
  testWidgets('renders the Salora logo', (WidgetTester tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: Center(child: AppLogo())),
      ),
    );

    expect(find.text('Salora'), findsOneWidget);
    expect(find.text('خطط • احجز • احتفل'), findsOneWidget);
  });
}
