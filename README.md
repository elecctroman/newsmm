# BHE Digital Ürün ve Fiyat Listesi

Bu depo, BHE Digital ürün ve hizmet portföyünü listeleyen hafif bir PHP uygulaması içerir. Uygulama tek dosyalı PHP kurulumlarında ve cPanel benzeri paylaşımlı hostinglerde sorunsuz çalışacak şekilde tasarlandı. Veri katmanı MySQL (MariaDB) kullanır; listeleme arayüzü Tailwind CSS CDN ve küçük bir JavaScript modülüyle güçlendirilmiştir.

## Gereksinimler

- PHP 8.0 veya üzeri (PDO MySQL uzantısı etkin)
- MySQL/MariaDB veritabanı
- Paylaşımlı hostinglerde standart olarak sağlanan Apache/Nginx + mod_php veya FPM kurulumu

## Kurulum Adımları

1. **Depoyu kopyalayın veya indirin.**
2. Sunucunuzda bir MySQL veritabanı ve kullanıcı oluşturun.
3. `database/schema.sql` dosyasını çalıştırarak tablo yapısını ve örnek verileri yükleyin.
4. `config/config.example.php` dosyasını `config/config.php` olarak kopyalayın ve veritabanı bilgilerinizi girin.
5. Tüm dosyaları web kök dizininize (örneğin `public_html`) aktarın. Gerekirse `public/assets` klasörünün okunabilir olduğundan emin olun.

Kurulum tamamlandığında `index.php` dosyası fiyat listesi sayfasını otomatik olarak oluşturur. Veritabanı bağlantısı yapılamazsa kullanıcıya açıklayıcı bir uyarı gösterilir.

## Özellikler

- Kategori bazlı filtreleme ve isteğe bağlı arama
- Fiyatı artan/azalan sıralama
- Instagram hesap ve takipçi sekmeleri
- Karanlık/aydınlık tema desteği (yerel depolama ile kalıcı)
- Paylaşımlı hosting uyumlu, derleme gerektirmez

## Veritabanı Şeması

Veritabanı iki tablodan oluşur:

- `categories`: Kategori meta bilgilerini saklar (`slug`, `emoji`, vb.).
- `products`: Ürün adı, fiyat etiketi, sıralama ve isteğe bağlı not alanlarını içerir.

`schema.sql` dosyası tüm örnek kayıtları içerir. Kendi fiyat listenize göre satırları güncelleyebilirsiniz.

## Dağıtım İpuçları

- Güvenlik için `config/config.php` dosyasını versiyon kontrolüne dahil etmeyin (repo `.gitignore` dosyasında hariç tutulmuştur).
- Tailwind CDN kullanıldığı için ekstra derleme ya da Node.js bağımlılığı gerekmez.
- İçerik güncellemeleri için yalnızca veritabanındaki kayıtları değiştirmeniz yeterlidir.

## Geliştirme

Yerel geliştirme için PHP yerleşik sunucusunu kullanabilirsiniz:

```bash
php -S localhost:8000
```

Bu komut, proje kök dizininde çalıştırıldığında `index.php` dosyasını sunar.
