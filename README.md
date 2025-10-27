# Lisansonay Yönetim Paneli

Lisansonay yönetim paneli, lisans ve sosyal medya hesap satışlarını uçtan uca yönetebilmeniz için tasarlanmış saf PHP + MySQL tabanlı bir uygulamadır. Modern bir admin arayüzü, stok ve sipariş metrikleri, yetkilendirilmiş erişim ve kapsamlı CRUD ekranları ile cPanel benzeri paylaşımlı hosting ortamlarında derleme gerektirmeden çalışır.

## Gereksinimler

- PHP 8.0 veya üzeri (PDO MySQL uzantısı etkin)
- MySQL/MariaDB veritabanı
- Apache/Nginx üzerinde mod_php veya PHP-FPM
- Paylaşımlı hostinglerde varsayılan olarak sunulan dosya sistemi izinleri

## Kurulum Adımları

1. Depoyu sunucunuza kopyalayın veya ZIP olarak indirip çıkarın.
2. MySQL üzerinde bir veritabanı ve gerekli yetkilere sahip kullanıcı oluşturun.
3. `database/schema.sql` dosyasını çalıştırarak tablo yapısını ve örnek kayıtları yükleyin.
4. `config/config.example.php` dosyasını `config/config.php` olarak kopyalayın ve veritabanı erişim bilgilerinizi girin.
5. Tüm dosyaları web kök dizininize (örn. `public_html`) aktarın. `public/assets` dizininin okunabilir olduğundan emin olun.
6. Tarayıcıdan `index.php` adresine giderek varsayılan yönetici hesabı ile ( `admin@lisansonay.com / Lisansonay!2025` ) giriş yapın ve şifrenizi değiştirin.

Kurulum tamamlandığında veritabanı bağlantısında sorun yaşanırsa arayüz, yapılandırma dosyasını veya kimlik bilgilerini kontrol etmeniz gerektiğini belirten uyarı bileşenleri gösterir.

## Özellikler

- **Kimlik Doğrulama ve Profil Yönetimi:** Oturum açma, çıkış, profil güncelleme ve şifre yenileme desteği.
- **Gösterge Paneli:** Kategori, ürün, sipariş ve gelir metrikleri; son işlemler ve iletişim kartları.
- **Ürün ve Kategori CRUD:** Sıralama, not, fiyat etiketi gibi alanlarla tam kapsamlı yönetim formları ve toplu listeler.
- **Sipariş Takibi:** Manuel sipariş oluşturma, durum yönetimi, müşteri iletişim bilgileri ve not alanı.
- **Aktivite Günlüğü:** Kategoriler, ürünler, siparişler ve ayarlardaki değişiklikleri kayıt altına alan log ekranı.
- **Genel Ayarlar:** Firma adı, destek e-posta/telefonu ve WhatsApp bağlantısı gibi temel bilgileri güncelleme.
- **Tema Desteği:** Kullanıcı tercihini tarayıcıda saklayan aydınlık/karanlık mod anahtarı.
- **Paylaşımlı Hosting Uyumu:** Tailwind CDN ve vanilla JavaScript ile derleme gerektirmeyen dağıtım.

## Veritabanı Şeması

`database/schema.sql` dosyası aşağıdaki tabloları içerir ve örnek verilerle birlikte gelir:

- `admin_users`: Yönetici hesapları (varsayılan olarak bir adet sahip hesabı eklenir).
- `settings`: Panelde kullanılan şirket ve destek bilgileri.
- `categories`: Katalog kategorileri.
- `products`: Ürünler ve fiyat bilgileri.
- `orders`: Sipariş durumları ve müşteri detayları.
- `activity_logs`: Yönetici aksiyonlarının geçmişi.

İhtiyacınıza göre bu dosyayı düzenleyerek varsayılan verileri özelleştirebilirsiniz.

## Yerel Geliştirme ve Test

Yerel ortamda hızlı test için PHP yerleşik sunucusunu kullanabilirsiniz:

```bash
php -S localhost:8000
```

Komutu proje kök dizininde çalıştırdıktan sonra tarayıcıdan `http://localhost:8000` adresine giderek paneli görüntüleyebilirsiniz.

## Güvenlik Önerileri

- `config/config.php` dosyasını versiyon kontrolüne dahil etmeyin (repo `.gitignore` dosyasında hariç tutulmuştur).
- İlk girişte varsayılan yönetici parolasını değiştirin.
- Panel URL'sini ek bir HTTP kimlik doğrulaması veya WAF kuralı ile korumanız önerilir.
- Paylaşımlı hostinglerde dizin izinlerini düzenleyerek gereksiz yazma yetkilerini kaldırın.

## Standart İş Akışı

1. Yeni kategori ve ürünleri **Kategoriler** / **Ürünler** ekranlarından ekleyin.
2. Müşteri taleplerini **Siparişler** bölümünden kayıt altına alın, durumlarını yönetin.
3. Kritik değişiklikleri **Aktivite** ekranından takip edin.
4. Şirket iletişim bilgilerini **Ayarlar** ekranından güncel tutun.

Bu yapı sayesinde Lisansonay operasyon ekibi, lisans ve sosyal medya hizmetlerine ait kayıtları tek panelden yönetebilir.
