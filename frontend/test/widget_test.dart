import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:frontend/main.dart';

void main() {
  testWidgets('App loads home screen', (WidgetTester tester) async {
    await tester.pumpWidget(
      const ProviderScope(
        child: ResumeTailorApp(),
      ),
    );

    // Verify the home screen loads with Get Started button
    expect(find.text('Get Started'), findsOneWidget);
    expect(find.text('AI Resume\nTailor'), findsOneWidget);
  });
}
