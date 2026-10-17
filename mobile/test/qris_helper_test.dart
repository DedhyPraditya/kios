import 'package:flutter_test/flutter_test.dart';
import 'package:kios_berkah_mobile/core/utils/qris_helper.dart';

void main() {
  test('QrisHelper correctly generates dynamic QRIS and CRC16', () {
    const staticQris =
        '00020101021126590013ID.CO.BNI.WWW011893600009150471362202096111903340303UMI51440014ID.CO.QRIS.WWW0215ID10265451804280303UMI5204541153033605802ID5919KIOS SEMBAKO BERKAH6007BANGGAI61059476262070703A0163043EAB';

    expect(QrisHelper.isValid(staticQris), isTrue);
    expect(QrisHelper.getMerchantName(staticQris), 'KIOS SEMBAKO BERKAH');

    final dynamicQris = QrisHelper.toDynamic(staticQris, 15000);
    expect(QrisHelper.isValid(dynamicQris), isTrue);
    expect(dynamicQris, contains('010212')); // tag 01 is 12 (dynamic)
    expect(dynamicQris, contains('540515000')); // tag 54 is 15000
    expect(dynamicQris.endsWith('BFE0'), isTrue); // CRC16 match
  });
}
