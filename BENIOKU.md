# Flarum için Diff

[![MIT lisansı](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/huseyinfiliz/flarum-diff/blob/master/LICENSE) [![Son Stabil Sürüm](https://img.shields.io/packagist/v/huseyinfiliz/flarum-diff.svg)](https://packagist.org/packages/huseyinfiliz/flarum-diff) [![Toplam İndirme](https://img.shields.io/packagist/dt/huseyinfiliz/flarum-diff.svg)](https://packagist.org/packages/huseyinfiliz/flarum-diff)

Bu eklenti [Flarum](https://github.com/flarum) forumunuza "düzenleme geçmişi" özelliği eklemenizi sağlar.

> **Not:** Bu, orijinal [the-turk/flarum-diff](https://github.com/the-turk/flarum-diff) eklentisinin Flarum 1.x için bakımı sürdürülen fork'udur. Flarum 2.x kullanıyorsanız lütfen [2.x uyumlu sürümü](https://github.com/huseyinfiliz/flarum-diff/tree/flarum-2.x) kullanın.

Ekran görüntüleri:

![Kolaj](https://i.ibb.co/FJywHKn/rsz-diff-collage.png)

- [Mesaj Görünümü](https://i.ibb.co/4m21pnM/post-Stream-Item.png)
- [Revizyon Listesi](https://i.ibb.co/PTTcWCw/dropdown-List.png)

## Özellikler

- [jfcherng/php-diff](https://github.com/jfcherng/php-diff) tabanlıdır (bu kütüphane artık bakımı yapılmayan [chrisboulton/php-diff](https://github.com/chrisboulton/php-diff) reposundan fork'lanmıştır).
- **satır** (varsayılan), **kelime** ve **karakter** seviyesindeki farklılıkları hesaplayabilir.
- "Aynı hizada", "Yan yana" ve "Kombine" olmak üzere üç ayrı gösterim modu vardır.
- Eski düzenlemeleri elle ya da zamanlanmış görev kullanarak arşivleyebilirsiniz.
- Düzenleme geçmişlerini silebilir ya da eski bir düzenlemeye geri dönebilirsiniz.
- `fof/nightmode` ve `the-turk/flarum-quiet-edits` eklentilerini destekler.
- [css-grid](https://caniuse.com/#feat=css-grid) modülünü destekleyen bütün tarayıcılarda çalışır.

Ayrıca "Düzenlendi" butonuna tıklanana kadar herhangi bir şey yüklenmez (ve önbelleğe alınmaz), dolayısıyla yüklenme süresi konusunda endişelenmenize gerek yoktur.

## Gereksinimler

![php](https://img.shields.io/badge/php-%E2%89%A57.4.0-blue?style=flat-square) ![ext-iconv](https://img.shields.io/badge/ext-iconv-brightgreen?style=flat-square)

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

## Kullanım

Eklentiyi aktif edin ve izinleri ayarlayın. Kullanmaya başlayabilirsiniz!

### Eski Düzenlemeleri Arşivlemek

**x** mesajın düzenlenme sayısı olmak üzere, **x ≥ A** koşulu sağlandığında mesaja ait ilk **y=mx+b** düzenlemeyi birleştirip sıkıştırarak yeni bir tabloda (`post_edit_histories_archive`) `BLOB` tipinde saklayabilirsiniz. **A**, **m** ve **b** değerlerini eklentinin ayarlarından belirleyin. Ondalık **y** değerleri en yakın alt tam sayıya yuvarlanacaktır. Depolama alanından tasarruf etmek istiyorsanız eski düzenlemeleri arşivlemeniz önerilir ancak _depolama alanı sıkıntınız yoksa önerilmez_.

Eski düzenlemeleri arşivlemek istiyorsanız _zamanlanmış görev seçeneğini_ aktif edebilirsiniz. Bu görev `diff:archive` komutunu kullanarak her hafta pazar günü sabah saat 02:00'de çalışır. Zamanlanmış görev kullanmazsanız, mesajın her düzenlemesinden sonra mesaja ait eski düzenlemeler taranır ve arşivlenir. Diğer bir seçenek de `php flarum diff:archive` komutunu kullanarak eski düzenlemeleri elle arşivlemektir. Zamanlanmış görev kurulumu için [buradaki tartışmayı](https://discuss.flarum.org/d/24118-setup-the-flarum-scheduler-using-cron) okuyabilirsiniz.

> **Not:** Linux sunucunuza eklemeniz gereken tek Cron girdisi şudur:
>
> `* * * * * php /<flarum/yolu>/flarum schedule:run >> /dev/null 2>&1`
>
> Bu Cron, Laravel komut zamanlayıcısını her dakika çağırır. Laravel de zamanlanmış görevlerinizi değerlendirerek zamanı gelen görevleri çalıştırır.

## Bağlantılar

- [Discuss](https://discuss.flarum.org/d/38490-diff-for-flarum-2x-new)
- [GitHub](https://github.com/huseyinfiliz/flarum-diff)
- [Sorun Bildir](https://github.com/huseyinfiliz/flarum-diff/issues)
- [Packagist](https://packagist.org/packages/huseyinfiliz/flarum-diff)