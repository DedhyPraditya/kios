import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:kios_berkah_mobile/app.dart';

void main() {
  testWidgets('App smoke test loads login screen', (WidgetTester tester) async {
    await tester.pumpWidget(
      const ProviderScope(
        child: KiosBerkahApp(),
      ),
    );

    expect(find.text('Kios BERKAH'), findsOneWidget);
    expect(find.text('Masuk Kasir'), findsOneWidget);
  });
}
