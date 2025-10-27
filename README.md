# Lisansonay Yönetim Paneli

Bu depo, Lisansonay ekiplerinin lisans, sosyal medya hesabı ve takipçi hizmetlerini profesyonel bir kontrol paneli üzerinden yönetebilmesi için tasarlanmış saf PHP + MySQL tabanlı uygulamayı içerir. Arayüz, modern bir admin panel deneyimi sunar ve cPanel benzeri paylaşımlı hosting ortamlarında ek derleme adımı gerektirmeden çalışır.

## Gereksinimler

- PHP 8.0 veya üzeri (PDO MySQL uzantısı etkin)
- MySQL/MariaDB veritabanı
- Paylaşımlı hostinglerde standart olarak sunulan Apache/Nginx + mod_php veya PHP-FPM yapılandırması

## Kurulum Adımları

1. Depoyu sunucunuza kopyalayın veya ZIP olarak indirip çıkarın.
2. MySQL üzerinde bir veritabanı ve gerekli yetkilere sahip kullanıcı oluşturun.
3. `database/schema.sql` dosyasını çalıştırarak tablo yapısını ve örnek kayıtları yükleyin.
4. `config/config.example.php` dosyasını `config/config.php` olarak kopyalayın ve veritabanı erişim bilgilerinizi doldurun.
5. Tüm dosyaları web kök dizininize (ör. `public_html`) aktarın. `public/assets` klasörünün okunabilir olduğundan emin olun.

Kurulum tamamlandığında `index.php` dosyasını tarayıcınızda açarak yönetim paneline ulaşabilirsiniz. Veritabanı bağlantısı sağlanamazsa sistem, sorun giderme adımlarını açıklayan uyarı bileşeni gösterir.

## Öne Çıkan Özellikler

- **Profesyonel arayüz:** Sol navigasyon menüsü, üst bar, metrik kartları ve tablo tabanlı ürün listesi ile modern yönetim paneli deneyimi.
- **Gelişmiş filtreleme:** Kategori rozeti, anahtar kelime araması, fiyat sıralaması ve Instagram özel sekmeleriyle kapsamlı filtre desteği.
- **Anlık içgörüler:** Portföy özet kartı en yüksek/uygun fiyatlı ürünleri ve takipçi paket sayılarını otomatik olarak raporlar.
- **Tema desteği:** Aydınlık/Karanlık modu kullanıcı tercihine göre kaydedilir.
- **Paylaşımlı hosting uyumu:** Tailwind CDN ve vanilla JavaScript kullanıldığı için derleme veya Node.js bağımlılığı yoktur.

## Veritabanı Şeması

Uygulama iki temel tablodan oluşur:

- `categories`: Kategori meta bilgilerini saklar (`slug`, `emoji`, açıklama, sıralama).
- `products`: Ürün adı, fiyat etiketi, fiyat kuruş değeri, isteğe bağlı not ve sıralama bilgilerini içerir.

`database/schema.sql` dosyası örnek verilerle birlikte gelir; kendi portföyünüze göre güncelleyebilirsiniz.

## Geliştirme ve Yerel Test

Yerel ortamda hızlı test için PHP yerleşik sunucusunu kullanabilirsiniz:

```bash
php -S localhost:8000
```

Komutu proje kök dizininde çalıştırdıktan sonra tarayıcıdan `http://localhost:8000` adresine giderek paneli görüntüleyebilirsiniz.

## Yapılandırma Güvenliği

- `config/config.php` dosyasını versiyon kontrolüne dahil etmeyin (repo `.gitignore` dosyasında hariç tutulmuştur).
- Paylaşımlı hostinglerde dizin izinlerini kontrol edin ve gereksiz yazma haklarını kaldırın.
- Yönetim panelini şifre koruması (ör. `.htpasswd`) veya WAF/Cloudflare kuralı ile korumak tavsiye edilir.
