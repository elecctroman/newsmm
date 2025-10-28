# Lisansonay Platformu

Lisansonay platformu, lisans ve sosyal medya hesap operasyonlarınızı uçtan uca yönetebilmeniz için tasarlanmış saf PHP + MySQL tabanlı bir çözümdür. Kök dizindeki müşteri portalı, canlı katalog ve sipariş formu deneyimi sunarken `/admin` altında yer alan gelişmiş yönetim paneli stok, sipariş ve yapılandırmaları kontrol etmenize imkân tanır. Tüm katmanlar cPanel benzeri paylaşımlı hosting ortamlarında derleme gerektirmeden çalışır.

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
6. Tarayıcıdan `index.php` adresine giderek müşteri portalının sorunsuz çalıştığını doğrulayın.
7. Yönetim işlemleri için `admin/index.php` adresine giderek varsayılan yönetici hesabı ile ( `admin@lisansonay.com / Lisansonay!2025` ) giriş yapın ve şifrenizi değiştirin.

Kurulum tamamlandığında veritabanı bağlantısında sorun yaşanırsa arayüz, yapılandırma dosyasını veya kimlik bilgilerini kontrol etmeniz gerektiğini belirten uyarı bileşenleri gösterir.

## Özellikler

- **Müşteri Portalı:** Kategori bazlı filtreleme, arama ve fiyat sıralama destekli ürün katalogu, WhatsApp ile hızlı iletişim ve CSRF korumalı sipariş talep formu.
- **Müşteri Hesapları:** Kayıt ol / giriş yap, profil düzenleme, sipariş geçmişi, açık destek talepleri ve güvenli çıkış işlemleri.
- **Yönetici Kimlik Doğrulaması:** `/admin` altında oturum açma, çıkış, profil güncelleme ve şifre yenileme desteği.
- **Gösterge Paneli:** Kategori, ürün, sipariş ve gelir metrikleri; son işlemler ve iletişim kartları.
- **Ürün ve Kategori CRUD:** Sıralama, not, fiyat etiketi gibi alanlarla tam kapsamlı yönetim formları ve toplu listeler.
- **RBAC ve Yetkilendirme:** Süper yönetici, içerik yöneticisi, editör, satış operasyonu ve gözlemci rollerini kapsayan ayrıntılı izin setleri; modül bazlı görünürlük ve işlem kısıtlamaları.
- **Vitrin Yönetimi:** Slider, bilgi şeritleri ve kategori blokları için sürükle-bırak sıralama, zamanlama ve yayın/taslak durumları; müşteri arayüzünde gerçek zamanlı yansıtma.
- **Sipariş Takibi:** Müşteri portalından gelen ve manuel eklenen talepler için durum yönetimi, iletişim bilgileri, müşteri hesabı eşleştirme ve not alanı.
- **Destek Merkezi:** Müşteri tarafında talep oluşturma/yanıtlama, yönetici panelinde canlı yanıt, durum güncelleme ve öncelik takibi.
- **Aktivite Günlüğü:** Kategoriler, ürünler, siparişler ve ayarlardaki değişiklikleri kayıt altına alan log ekranı.
- **Genel Ayarlar:** Firma adı, destek e-posta/telefonu ve WhatsApp bağlantısı gibi temel bilgileri güncelleme.
- **Tema Desteği:** Kullanıcı tercihini tarayıcıda saklayan aydınlık/karanlık mod anahtarı (portal ve panel için ortak).
- **Paylaşımlı Hosting Uyumu:** Tailwind CDN ve vanilla JavaScript ile derleme gerektirmeyen dağıtım.

## Veritabanı Şeması

`database/schema.sql` dosyası aşağıdaki tabloları içerir ve örnek verilerle birlikte gelir:

- `admin_users`: Yönetici hesapları (varsayılan olarak bir adet sahip hesabı eklenir).
- `settings`: Panelde kullanılan şirket ve destek bilgileri.
- `roles`, `permissions`, `role_permissions`, `admin_user_roles`: Rol ve izin yönetimi altyapısı.
- `categories`: Özellik, ikon ve SEO alanlarıyla birlikte katalog kategorileri.
- `products`: Genişletilmiş fiyat, stok, rozet ve medya alanlarına sahip ürün kayıtları.
- `customer_users`: Portal oturumları için müşteri hesapları.
- `orders`: Sipariş durumları, müşteri detayları ve hesap bağlantıları.
- `activity_logs`: Yönetici aksiyonlarının geçmişi.
- `support_tickets`: Müşteri destek talepleri.
- `support_messages`: Müşteri ve destek ekibi mesajlaşmaları.
- `home_slider_items`, `home_strip_items`, `home_blocks`: Ana sayfa vitrin bileşenleri ve sıralama bilgileri.
- `blog_posts`: Blog içeriği (opsiyonel vitrin şeridi için).
- `menus`, `menu_items`: Üst menü ve özel bağlantı yönetimi.
- `redirects`: 301/302 yönlendirme kayıtları.
- `media_files`: Medya kütüphanesi meta verileri.

İhtiyacınıza göre bu dosyayı düzenleyerek varsayılan verileri özelleştirebilirsiniz.

## Yerel Geliştirme ve Test

Yerel ortamda hızlı test için PHP yerleşik sunucusunu kullanabilirsiniz:

```bash
php -S localhost:8000
```

Komutu proje kök dizininde çalıştırdıktan sonra tarayıcıdan `http://localhost:8000` adresine giderek paneli görüntüleyebilirsiniz.

Vitrin ve yardımcı fonksiyon doğrulamaları için şu betiği kullanın:
```bash
php tests/run.php
```

Betiğin tüm kontrolleri başarıyla tamamlaması para/slug yardımcılarının yanı sıra RBAC izin çözümleri ve vitrin blok derlemesinin beklenen çıktıları ürettiğini doğrular.

## Güvenlik Önerileri

- `config/config.php` dosyasını versiyon kontrolüne dahil etmeyin (repo `.gitignore` dosyasında hariç tutulmuştur).
- İlk girişte varsayılan yönetici parolasını değiştirin.
- Panel URL'sini ek bir HTTP kimlik doğrulaması veya WAF kuralı ile korumanız önerilir.
- Paylaşımlı hostinglerde dizin izinlerini düzenleyerek gereksiz yazma yetkilerini kaldırın.

## Standart İş Akışı

1. Yeni kategori ve ürünleri **Kategoriler** / **Ürünler** ekranlarından ekleyin.
2. Müşteri taleplerini **Siparişler** bölümünden kayıt altına alın, durumlarını yönetin.
3. Destek taleplerini **Destek** ekranından yanıtlayın ve durum değişikliklerini izleyin.
4. Kritik değişiklikleri **Aktivite** ekranından takip edin.
5. Şirket iletişim bilgilerini **Ayarlar** ekranından güncel tutun.

Bu yapı sayesinde Lisansonay operasyon ekibi, lisans ve sosyal medya hizmetlerine ait kayıtları tek panelden yönetebilir.
