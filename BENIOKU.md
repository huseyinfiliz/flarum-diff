# Flarum 2.x için Diff

[![MIT lisansı](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/huseyinfiliz/flarum-diff/blob/master/LICENSE) [![Son Stabil Sürüm](https://img.shields.io/packagist/v/huseyinfiliz/flarum-diff.svg)](https://packagist.org/packages/huseyinfiliz/flarum-diff) [![Toplam İndirme](https://img.shields.io/packagist/dt/huseyinfiliz/flarum-diff.svg)](https://packagist.org/packages/huseyinfiliz/flarum-diff)

Bu eklenti [Flarum](https://github.com/flarum) forumunuza "düzenleme geçmişi" özelliği eklemenizi sağlar.

> **Not:** Bu, orijinal [the-turk/flarum-diff](https://github.com/the-turk/flarum-diff) eklentisinin (Flarum 1.8.x destekli) Flarum 2.x uyumlu sürümüdür. Bu fork, Flarum 2.0 ve sonraki sürümlerle çalışacak şekilde güncellenmiştir.

Ekran görüntüleri:

![Kolaj](https://i.ibb.co/FJywHKn/rsz-diff-collage.png)

- [Mesaj Görünümü](https://i.ibb.co/4m21pnM/post-Stream-Item.png)
- [Revizyon Listesi](https://i.ibb.co/PTTcWCw/dropdown-List.png)

## Özellikler

- [jfcherng/php-diff](https://github.com/jfcherng/php-diff) tabanlıdır.
- **satır** (varsayılan), **kelime** ve **karakter** seviyesindeki farklılıkları hesaplayabilir.
- "Aynı hizada", "Yan yana" ve "Kombine" olmak üzere üç ayrı gösterim modu vardır.
- Eski düzenlemeleri elle ya da zamanlanmış görev kullanarak arşivleyebilirsiniz.
- Düzenleme geçmişlerini silebilir ya da eski bir düzenlemeye geri dönebilirsiniz.
- `the-turk/flarum-quiet-edits` eklentisini destekler.
- [css-grid](https://caniuse.com/#feat=css-grid) modülünü destekleyen bütün tarayıcılarda çalışır.

## Gereksinimler

![php](https://img.shields.io/badge/php-%E2%89%A58.1-blue?style=flat-square) ![ext-iconv](https://img.shields.io/badge/ext-iconv-brightgreen?style=flat-square)

php sürümünüzü `php -v` komutunu çalıştırarak ve `iconv` paketinin yüklü olup olmadığını `php --ri iconv` komutunu çalıştırarak (`iconv support => enabled` çıktısını görmelisiniz) öğrenebilirsiniz.

## Kurulum

```bash
composer require huseyinfiliz/flarum-diff:"*"
```

## Güncelleme

```bash
composer update huseyinfiliz/flarum-diff
php flarum migrate
php flarum cache:clear
```

## the-turk/flarum-diff'ten Yükseltme

> ⚠️ **ÖNEMLİ: Veritabanı Yedeği Zorunludur**
> 
> Yükseltme yapmadan önce **veritabanınızı yedeklemeniz zorunludur**. Testlerimizde geçiş sırasında veri kaybı yaşanmamış olsa da, büyük sürüm yükseltmelerinde her zaman risk vardır. Revizyon geçmişi verileriniz `post_edit_histories` ve `post_edit_histories_archive` tablolarında saklanmaktadır.
>
> Testlerimizde, yükseltme sonrasında tüm revizyon verileri başarıyla korunmuştur.

> ℹ️ **Ayarların Aktarılması Hakkında**
>
> the-turk/flarum-diff (1.8.x) eklentisindeki ayarlar bu sürüme **aktarılmayacaktır**. Bunun sebebi ayar önekinin `the-turk-diff.*` yerine `huseyinfiliz-diff.*` olarak değişmiş olmasıdır.
>
> **Bu kritik bir sorun değildir** - eklentiyi kurduktan sonra ayarlarınızı admin panelinden manuel olarak yapılandırmanız yeterlidir. Bu durum sadece görüntüleme tercihlerini (detay seviyesi, komşu satırlar, birleştirme eşiği, arşivleme seçenekleri vb.) etkiler ve **revizyon geçmişi verilerinizi hiçbir şekilde etkilemez**. Saklanan tüm revizyonlarınız, siz özelleştirene kadar varsayılan ayarlarla normal şekilde çalışmaya devam edecektir.

Orijinal eklentiden yükseltme yapmak için:

1. **Veritabanınızı yedekleyin** (zorunlu)
2. Admin panelinden eski eklentiyi devre dışı bırakın
3. Eski eklentiyi kaldırın:
   ```bash
   composer remove the-turk/flarum-diff
   ```
4. Flarum'u 2.0'a yükseltin:
   ```bash
   composer require flarum/core:"^2.0"
   # Flarum'un yükseltme rehberini takip edin
   ```
5. Bu eklentiyi kurun:
   ```bash
   composer require huseyinfiliz/flarum-diff:"*"
   ```
6. Migration'ları çalıştırın ve önbelleği temizleyin:
   ```bash
   php flarum migrate
   php flarum cache:clear
   ```
7. Admin panelinden eklentiyi etkinleştirin
8. **Ayarlarınızı yapılandırın** - admin panelinden ayarlarınızı yapın (1.8.x'teki ayarlar aktarılmayacaktır)

**Özet:** Revizyon geçmişi verileriniz tamamen korunacaktır. Sadece eklenti ayarlarının manuel olarak yeniden yapılandırılması gerekmektedir.

## Kullanım

Eklentiyi aktif edin ve izinleri ayarlayın. Kullanmaya başlayabilirsiniz!

### Eski Düzenlemeleri Arşivlemek

**x** mesajın düzenlenme sayısı olmak üzere, **x ≥ A** koşulu sağlandığında mesaja ait ilk **y=mx+b** düzenlemeyi birleştirip sıkıştırarak yeni bir tabloda (`post_edit_histories_archive`) `BLOB` tipinde saklayabilirsiniz. **A**, **m** ve **b** değerlerini eklentinin ayarlarından belirleyin. Ondalık **y** değerleri en yakın alt tam sayıya yuvarlanacaktır. Depolama alanından tasarruf etmek istiyorsanız, eski düzenlemeleri arşivlemeniz önerilir ancak _depolama alanı sıkıntınız yoksa önerilmez_.

Eski düzenlemeleri arşivlemek istiyorsanız _zamanlanmış görev seçeneğini_ aktif edebilirsiniz. Bu görev `diff:archive` komutunu kullanarak her hafta pazar günü sabah saat 02:00'de çalışır. Zamanlanmış görev kullanmazsanız, mesajın her düzenlemesinden sonra mesaja ait eski düzenlemeler taranır ve arşivlenir. Diğer bir seçenek de `php flarum diff:archive` komutunu kullanarak eski düzenlemeleri elle arşivlemektir. Zamanlanmış görev kurulumu için [buradaki tartışmayı](https://discuss.flarum.org/d/24118-setup-the-flarum-scheduler-using-cron) okuyabilirsiniz.

## Bağlantılar

- [Flarum tartışma konusu](https://discuss.flarum.org/d/22779-diff-for-flarum)
- [GitHub üzerindeki kaynak kodu](https://github.com/huseyinfiliz/flarum-diff)
- [Değişiklikler](https://github.com/huseyinfiliz/flarum-diff/blob/master/CHANGELOG.md)
- [Sorun bildir](https://github.com/huseyinfiliz/flarum-diff/issues)
- [Packagist aracılığıyla indir](https://packagist.org/packages/huseyinfiliz/flarum-diff)
- [Orijinal eklenti (Flarum 1.x)](https://github.com/the-turk/flarum-diff)

## Katkıda Bulunanlar

- Orijinal eklenti: [Hasan Özbey (the-turk)](https://github.com/the-turk)
- Flarum 2.x uyumluluğu: [Hüseyin Filiz](https://github.com/huseyinfiliz)