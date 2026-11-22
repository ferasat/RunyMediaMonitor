# RunyMediaMonitor — رصدیار

سیستم جامع و ماژولار برای پایش شبکه‌های اجتماعی، وب‌سایت‌ها و منابع رسانه‌ای؛ شناسایی محتوای جدید، آرشیو، پردازش رسانه، تحلیل هوشمند و ارسال اعلان به ربات بله.

## هدف پروژه

RunyMediaMonitor یک **Social Media & Web Monitoring System** است، نه صرفاً یک اسکرپر. کاربر منابع مختلف را ثبت می‌کند و سیستم طبق زمان‌بندی آن‌ها را بررسی می‌کند، محتوای جدید را تشخیص می‌دهد، اطلاعات و رسانه‌های قابل دریافت را پردازش و آرشیو می‌کند و در صورت تنظیم، خروجی را به مقصدهایی مانند ربات بله می‌فرستد.

## قابلیت‌های اصلی

- مدیریت منابع و دسته‌بندی آن‌ها
- زمان‌بندی مستقل برای هر منبع
- شناسایی محتوای جدید و جلوگیری از ثبت یا ارسال تکراری
- دریافت Metadata، متن، Caption، لینک و Thumbnail
- دریافت رسانه فقط در صورت وجود دسترسی و مجوز مناسب
- آرشیو مرکزی محتوا و رسانه
- Content Inbox با جستجو و فیلتر
- ارسال اعلان به Bale Bot و مقصدهای قابل توسعه
- گزارش‌های روزانه و مانیتورینگ سلامت منابع
- پردازش اختیاری محتوا با هوش مصنوعی
- Logging، Retry، مدیریت خطا و Rate Limit
- معماری توسعه‌پذیر برای افزودن Source Adapterهای جدید

## منابع هدف

| منبع | قابلیت‌های مورد انتظار |
|---|---|
| Instagram | پایش صفحات قابل دسترس، Post، Reel، Carousel، Caption، لینک و Metadata |
| YouTube | Channel، Playlist، Video، Shorts، Live، عنوان، توضیحات، تاریخ، Thumbnail و Transcript در صورت دسترسی |
| Website | RSS، Atom، Sitemap، مقاله، تصاویر، ویدیوهای Embed، Open Graph و Structured Data |
| Telegram | کانال‌ها و پیام‌های قابل دسترس، متن، تصویر، ویدیو، فایل و Media Group |
| Bale | منابع قابل دسترس از طریق API یا روش مجاز |
| X / Twitter | پست‌ها و رسانه‌ها در صورت فراهم بودن API یا دسترسی مجاز |
| سایر منابع | افزودن Adapter مستقل بدون تغییر هسته |

## معماری کلان

هسته سیستم باید مستقل از پلتفرم‌ها باشد. برای هر منبع یک Adapter مستقل با قرارداد مشخص طراحی شود.

### جریان پردازش

1. ثبت Source و تنظیمات آن
2. اجرای زمان‌بندی‌شده Source Check
3. کشف محتواهای جدید
4. Deduplication با شناسه خارجی، Canonical URL یا Hash
5. ذخیره Metadata در دیتابیس
6. دریافت رسانه، فقط در صورت امکان و مجاز بودن
7. پردازش رسانه و استخراج اطلاعات
8. پردازش AI به‌صورت اختیاری
9. ارسال اعلان به مقصدهای تنظیم‌شده
10. ثبت Log، وضعیت و خطا

### قرارداد Source Adapter

هر Adapter باید قابلیت‌های خودش را اعلام کند و عملیات مرتبط را مستقل پیاده‌سازی کند:

- Validate Source
- Fetch Source Metadata
- Discover New Content
- Fetch Content Metadata
- Fetch Media، در صورت پشتیبانی و مجاز بودن
- Fetch Transcript، در صورت دسترسی
- Detect Content Type
- Handle Rate Limits
- Handle Authentication Requirements
- Report Capabilities and Errors

## تفاوت مهم دریافت رسانه در YouTube و Instagram

YouTube باید Adapter و Media Service مستقل داشته باشد؛ روش دریافت اطلاعات و رسانه آن با Instagram یا سایر منابع یکسان فرض نشود.

### YouTube Adapter

اطلاعات مورد انتظار:
- Channel ID و Channel Name
- Video ID و لینک اصلی
- Title و Description
- Publish Date
- Thumbnail
- Duration، در صورت دسترسی
- تشخیص Video، Shorts و Live
- Playlist Monitoring
- Transcript یا Subtitle، در صورت دسترسی مجاز

سیاست دریافت رسانه:
- ابتدا Metadata از API رسمی و مستند دریافت شود.
- Thumbnail و Transcript در صورت دسترسی دریافت شوند.
- فایل ویدیو فقط از روش‌های رسمی و مجاز یا برای محتوایی که کاربر مالک آن است یا اجازه دریافت آن را دارد، دریافت شود.
- از دور زدن DRM، Authentication یا مکانیزم‌های حفاظتی خودداری شود.
- اگر دریافت فایل ممکن یا مجاز نبود، محتوا حذف نشود؛ Metadata، Thumbnail، لینک و وضعیت دریافت رسانه ذخیره و گزارش شوند.
- وضعیت Media Retrieval از وضعیت Content Monitoring مستقل باشد.

### Instagram Adapter

- Adapter مستقل از هسته
- پایش صفحات و محتوای قابل دسترس، در چارچوب دسترسی و شرایط سرویس
- تشخیص Post، Reel و Carousel
- دریافت Caption، لینک، تاریخ و Metadata در صورت دسترسی
- دریافت عکس و ویدیو فقط در صورت دسترسی و مجاز بودن
- مدیریت خطاها و تغییرات احتمالی پلتفرم
- عدم دسترسی به محتوای خصوصی یا دور زدن مکانیزم‌های حفاظتی

### Web / RSS Adapter

- RSS و Atom
- Sitemap
- Article Extraction
- Title، Body، Author و Published Date
- Canonical URL
- Open Graph و Structured Data
- Image و Embedded Media
- رعایت محدودیت‌های دسترسی، Crawl Interval و جلوگیری از درخواست‌های غیرضروری

## Media Processing Pipeline

پردازش رسانه مستقل از Adapterها باشد و امکانات زیر را فراهم کند:

- بررسی MIME Type و حجم فایل
- Hash و تشخیص فایل تکراری
- Storage Path و مدیریت Storage Driver
- تولید Thumbnail و استخراج Metadata
- ابعاد تصویر و مدت ویدیو
- مدیریت فایل ناقص، خراب و خطا
- مدیریت فایل‌های موقت و Cleanup
- پشتیبانی از Local Storage و S3-Compatible Storage

اطلاعات رسانه می‌تواند شامل Original URL، Storage Path، MIME Type، Size، Hash، Duration، Dimensions، Download Status، Processing Status و Error Message باشد.

## تکنولوژی پیشنهادی

- Laravel 13
- PHP 8.3+
- MySQL
- Redis
- Laravel Queue و Scheduler
- Livewire
- Bootstrap 5
- Vite
- FFmpeg در صورت نیاز به پردازش رسانه
- Docker برای محیط قابل استقرار (اختیاری)

نسخه‌ها و سازگاری Dependencyها هنگام شروع پیاده‌سازی بررسی شوند؛ از نصب پکیج‌های غیرضروری خودداری شود.

## ماژول‌های پیشنهادی

1. Dashboard
2. Sources
3. Source Categories
4. Source Adapters
5. Content Inbox
6. Media Library
7. Media Processing
8. Notifications
9. Bale Bot Integration
10. AI Providers
11. Filters
12. Daily Reports
13. Logs & Monitoring
14. System Settings

## مدیریت منابع

هر Source حداقل می‌تواند شامل این اطلاعات باشد:

- Type، Name، URL، External ID
- Username / Handle، Category و Description
- Active Status و Check Interval
- Last Checked At و Last Successful Check At
- Last Error و Configuration
- Credentials با ذخیره‌سازی امن
- Media Settings و Notification Settings

کاربر باید بتواند Source را اضافه، ویرایش، حذف، فعال یا غیرفعال، دسته‌بندی و دستی بررسی کند.

## Content Inbox

تمام محتواها در یک بخش مرکزی قابل مشاهده باشند:

- جستجو و فیلتر بر اساس منبع، دسته، تاریخ، نوع رسانه و وضعیت
- نمایش Caption، متن، Transcript، Metadata و لینک اصلی
- مشاهده عکس و ویدیو در صورت ذخیره شدن
- مشاهده وضعیت پردازش و ارسال
- ارسال مجدد اعلان
- آرشیو، حذف و علامت‌گذاری محتوای مهم

انواع محتوا: Text، Image، Video، Audio، Document، Carousel، Live، Article و Unknown.

## Bale Notification Service

از API رسمی و مستند بله استفاده شود.

قابلیت‌ها:
- Bot Token و Destination Chat/Channel
- پشتیبانی از چند مقصد
- تنظیم ارسال به‌ازای Source و Category
- ارسال متن، عکس، ویدیو، فایل، Caption، Thumbnail و لینک، متناسب با محدودیت API
- قالب پیام قابل تنظیم
- Queue-Based Sending
- بررسی محدودیت حجم و API
- Retry کنترل‌شده و جلوگیری از ارسال تکراری
- در صورت عدم امکان ارسال رسانه، ارسال متن و لینک و ثبت علت

نمونه قالب پیام:

```text
📢 محتوای جدید

📌 منبع: {source_name}
📰 عنوان: {title}
📅 تاریخ انتشار: {published_at}

📝 متن:
{caption}

🔗 لینک:
{original_url}

📷 نوع محتوا: {media_type}
```

## AI Processing (اختیاری)

Provider مستقل و قابل تنظیم:
- OpenAI-Compatible API
- DeepSeek
- سایر مدل‌ها
- Local Models در آینده

قابلیت‌ها:
- خلاصه‌سازی و دسته‌بندی
- استخراج Keywords و موجودیت‌ها
- تشخیص زبان و موضوع
- فیلتر معنایی و اولویت‌بندی محتوا
- تولید گزارش روزانه

خطای AI نباید دریافت، ذخیره یا ارسال محتوا را متوقف کند.

## Filters و Daily Reports

فیلترها می‌توانند بر اساس Keyword، Regex، Source، Category، Media Type و AI Semantic Filter تعریف شوند. فیلترها اولویت ارسال یا گزارش را تعیین کنند و باعث حذف دائمی محتوای اصلی نشوند.

گزارش روزانه شامل تعداد منابع بررسی‌شده، محتواهای جدید، انواع رسانه، اعلان‌های ارسال‌شده، خطاها، منابع دارای خطا، محتواهای مهم و خلاصه AI در صورت فعال بودن باشد.

## Database پیشنهادی

Schema نهایی پس از تحلیل نیازمندی‌ها طراحی شود. جداول اولیه:

- sources
- source_types
- source_categories
- source_category_assignments
- source_credentials
- source_check_logs
- contents
- content_media
- content_transcripts
- content_tags
- content_categories
- media_files
- media_processing_logs
- notification_destinations
- notification_templates
- notifications
- notification_logs
- filters
- filter_rules
- ai_processing_logs
- daily_reports
- system_logs

از JSON برای تنظیمات متغیر استفاده شود؛ اطلاعات اصلی و قابل جستجو بدون دلیل در JSON ذخیره نشوند.

## Scheduler، Queue و پایداری

- زمان‌بندی مستقل برای هر Source
- Intervalهای قابل تنظیم
- جلوگیری از Jobهای هم‌زمان روی یک Source
- Locks و Jobهای Idempotent
- Retry با Backoff و محدودیت تعداد تلاش
- Rate Limit Handling
- Queueهای جداگانه برای Source Check، Media، AI و Notification
- امکان ادامه امن پس از Restart

## امنیت و نگهداری

- Authentication، Authorization و Validation
- CSRF و Rate Limiting
- مدیریت امن Tokenها و عدم ثبت Secrets در Logs
- بررسی MIME Type و فایل‌های مخرب
- جلوگیری از Path Traversal و SSRF هنگام دریافت URL
- محدودیت حجم و تعداد دانلود
- کنترل دسترسی به Storage
- کنترل Concurrent Jobs و مصرف منابع
- Cache Metadata و جلوگیری از دریافت مجدد رسانه
- پاکسازی فایل‌های موقت و Retention Policy
- نگهداری Metadata مستقل از رسانه
- امکان حذف رسانه‌های قدیمی و حفظ اطلاعات محتوا

## تست

- Unit Tests برای Adapterها، Deduplication، Parser، Media، Filter و Notification
- Feature Tests برای Source Management، Content، Queue و ارسال
- Integration Tests با Mock سرویس‌های خارجی
- تست خطا، Retry، Rate Limit و Duplicate
- تست‌ها به‌صورت پیش‌فرض درخواست واقعی غیرضروری به سرویس خارجی ارسال نکنند.

## Roadmap

### Phase 1 — Core
Project Setup، Authentication، Dashboard، Source Management، Adapter Contract، Database، Queue، Scheduler و Logging.

### Phase 2 — Web & RSS
RSS / Atom، Sitemap، Website Adapter، Article Extraction، Content Inbox و Deduplication.

### Phase 3 — YouTube
YouTube API Integration، Channel / Playlist Monitoring، تشخیص Video / Shorts / Live، Metadata، Thumbnail، Transcript در صورت دسترسی، Media Retrieval مستقل و مجاز و مدیریت ویدیوهای غیرقابل دریافت.

### Phase 4 — Instagram
Source Management، تشخیص Post / Reel / Carousel، Caption و Metadata و Media Handling متناسب با دسترسی مجاز.

### Phase 5 — Telegram & Bale Sources
Adapterها، پیام‌ها، رسانه‌ها و Media Group.

### Phase 6 — Notifications
Bale Bot، ارسال متن و رسانه، Templates، Retry و Logs.

### Phase 7 — AI
Provider Integration، Summarization، Classification، Semantic Filters و Daily Reports.

### Phase 8 — Production
Security Review، Performance، Monitoring، Backup، Docker و Documentation.

## قواعد توسعه

1. ابتدا Repository و محیط موجود بررسی شود.
2. قبل از کدنویسی، Architecture Document و Roadmap ارائه شود.
3. هر فاز مستقل پیاده‌سازی و تست شود.
4. Migrationها برای تغییرات دیتابیس استفاده شوند.
5. فایل‌های موجود بدون بررسی حذف یا بازنویسی نشوند.
6. Secrets در Git Commit نشوند.
7. API و قابلیت‌های خارجی بدون بررسی مستندات و تست، پشتیبانی‌شده اعلام نشوند.
8. Metadata Retrieval از Media Retrieval مستقل باشد.
9. عدم امکان دریافت رسانه باعث از دست رفتن محتوا نشود.
10. تمام عملیات قابل ردیابی باشند.
11. از Dependencyهای غیرضروری و سرویس‌های پولی بدون بررسی اجتناب شود.
12. محدودیت‌ها و ریسک‌ها شفاف مستند شوند.
13. معماری برای Adapterهای جدید توسعه‌پذیر باشد.

## اولین اقدام Coding Agent

در اولین مرحله **کدنویسی انجام نده**. ابتدا:

1. Repository و محیط را بررسی کن.
2. معماری پیشنهادی را ارائه بده.
3. محدودیت API و دسترسی منابع را بررسی کن.
4. روش دریافت اطلاعات و رسانه هر Source را مشخص کن.
5. Database Schema پیشنهادی ارائه بده.
6. ساختار Adapterها را توضیح بده.
7. Roadmap و Dependencyهای ضروری را ارائه بده.
8. هزینه‌های احتمالی، ریسک‌ها و محدودیت‌ها را شناسایی کن.

پس از ارائه گزارش، برای شروع Phase 1 منتظر تأیید بمان.

---

**هدف نهایی:** ساخت یک سیستم پایدار، توسعه‌پذیر، ماژولار و امن برای پایش منابع اینترنتی، جمع‌آوری و آرشیو محتوا، پردازش رسانه، تحلیل هوشمند و ارسال اعلان.