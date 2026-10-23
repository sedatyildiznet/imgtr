# IMGTR — Nostr üzerinde dağıtık görsel paylaşımı

**IMGTR**, görselleri origin sunucusunda kalıcı olarak saklamak yerine istemci tarafında işleyip imzalı Nostr olaylarıyla birden fazla relay'e yayımlayan PHP tabanlı açık kaynak uygulamasıdır.

[Canlı site](https://imgtr.xyz) · [English README](README.md) · [API](https://imgtr.xyz/api) · [SDK](sdk/js/README.md)

## Özellikler

- Nostr üzerinde imzalı manifest ve görsel parçalarının birden fazla relay'e dağıtılması
- Event kimliği, parça hash'i ve dosya boyutu doğrulaması
- PHP 8.1+, MySQL/MariaDB ve Apache ile klasik hosting ortamında çalışma
- Kullanıcılar, albümler, koleksiyonlar, yönetim paneli ve çoklu dil
- REST API, OpenAPI, Node.js SDK/CLI ve ShareX entegrasyonu

## Kurulum notu

Bu sürüm mevcut çalışan IMGTR kurulumunun kaynak kodudur. **Sıfırdan kurulum için gereken tam SQL başlangıç şeması henüz paylaşılmamıştır.** `updates/` içindeki migration'lar tek başına yeterli değildir. Ayrıntılar: [Self-hosting](docs/SELF_HOSTING.md).

## Gizlilik

Nostr olaylarının imzalanması şifreleme anlamına gelmez. Herkese açık relay'lerdeki veriler üçüncü kişilerce okunabilir, kopyalanabilir ve silme isteğine rağmen saklanabilir. Açık kaynak kodlar canlı sitenin veritabanını veya kullanıcı kayıtlarını içermez.

**Lisans:** [MIT](LICENSE) · [Katkı rehberi](CONTRIBUTING.md) · [Güvenlik politikası](SECURITY.md)
