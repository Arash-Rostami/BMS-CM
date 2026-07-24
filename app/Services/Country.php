<?php

namespace App\Services;

use App;

class Country
{
    private array $nameIndexByLocale = [];

    private array $sortedListByLocale = [];

    /**
     * Internal array of country data.
     */
    private array $countries = [
        ['code' => 'AF', 'name' => 'افغانستان', 'name_english' => 'Afghanistan'],
        ['code' => 'AL', 'name' => 'آلبانی', 'name_english' => 'Albania'],
        ['code' => 'DZ', 'name' => 'الجزایر', 'name_english' => 'Algeria'],
        ['code' => 'AS', 'name' => 'ساموآی آمریکا', 'name_english' => 'American Samoa'],
        ['code' => 'AD', 'name' => 'آندورا', 'name_english' => 'Andorra'],
        ['code' => 'AO', 'name' => 'آنگولا', 'name_english' => 'Angola'],
        ['code' => 'AI', 'name' => 'آنگویلا', 'name_english' => 'Anguilla'],
        ['code' => 'AQ', 'name' => 'قطب جنوب', 'name_english' => 'Antarctica'],
        ['code' => 'AG', 'name' => 'آنتیگوا و باربودا', 'name_english' => 'Antigua and Barbuda'],
        ['code' => 'AR', 'name' => 'آرژانتین', 'name_english' => 'Argentina'],
        ['code' => 'AM', 'name' => 'ارمنستان', 'name_english' => 'Armenia'],
        ['code' => 'AW', 'name' => 'آروبا', 'name_english' => 'Aruba'],
        ['code' => 'AU', 'name' => 'استرالیا', 'name_english' => 'Australia'],
        ['code' => 'AT', 'name' => 'اتریش', 'name_english' => 'Austria'],
        ['code' => 'AZ', 'name' => 'آذربایجان', 'name_english' => 'Azerbaijan'],
        ['code' => 'BS', 'name' => 'باهاما', 'name_english' => 'Bahamas'],
        ['code' => 'BH', 'name' => 'بحرین', 'name_english' => 'Bahrain'],
        ['code' => 'BD', 'name' => 'بنگلادش', 'name_english' => 'Bangladesh'],
        ['code' => 'BB', 'name' => 'باربادوس', 'name_english' => 'Barbados'],
        ['code' => 'BY', 'name' => 'بلاروس', 'name_english' => 'Belarus'],
        ['code' => 'BE', 'name' => 'بلژیک', 'name_english' => 'Belgium'],
        ['code' => 'BZ', 'name' => 'بلیز', 'name_english' => 'Belize'],
        ['code' => 'BJ', 'name' => 'بنین', 'name_english' => 'Benin'],
        ['code' => 'BM', 'name' => 'برمودا', 'name_english' => 'Bermuda'],
        ['code' => 'BT', 'name' => 'بوتان', 'name_english' => 'Bhutan'],
        ['code' => 'BO', 'name' => 'بولیوی', 'name_english' => 'Bolivia (Plurinational State of)'],
        ['code' => 'BQ', 'name' => 'بونیر، سینت یوستاتیوس و سابا', 'name_english' => 'Bonaire, Sint Eustatius and Saba'],
        ['code' => 'BA', 'name' => 'بوسنی و هرزگوین', 'name_english' => 'Bosnia and Herzegovina'],
        ['code' => 'BW', 'name' => 'بوتسوانا', 'name_english' => 'Botswana'],
        ['code' => 'BV', 'name' => 'جزیره بووه', 'name_english' => 'Bouvet Island'],
        ['code' => 'BR', 'name' => 'برزیل', 'name_english' => 'Brazil'],
        ['code' => 'IO', 'name' => 'قلمرو بریتانیا در اقیانوس هند', 'name_english' => 'British Indian Ocean Territory'],
        ['code' => 'BN', 'name' => 'برونئی', 'name_english' => 'Brunei Darussalam'],
        ['code' => 'BG', 'name' => 'بلغارستان', 'name_english' => 'Bulgaria'],
        ['code' => 'BF', 'name' => 'بورکینافاسو', 'name_english' => 'Burkina Faso'],
        ['code' => 'BI', 'name' => 'بوروندی', 'name_english' => 'Burundi'],
        ['code' => 'CV', 'name' => 'کیپ ورد', 'name_english' => 'Cabo Verde'],
        ['code' => 'KH', 'name' => 'کامبوج', 'name_english' => 'Cambodia'],
        ['code' => 'CM', 'name' => 'کامرون', 'name_english' => 'Cameroon'],
        ['code' => 'CA', 'name' => 'کانادا', 'name_english' => 'Canada'],
        ['code' => 'KY', 'name' => 'جزایر کیمن', 'name_english' => 'Cayman Islands'],
        ['code' => 'CF', 'name' => 'جمهوری آفریقای مرکزی', 'name_english' => 'Central African Republic'],
        ['code' => 'TD', 'name' => 'چاد', 'name_english' => 'Chad'],
        ['code' => 'CL', 'name' => 'شیلی', 'name_english' => 'Chile'],
        ['code' => 'CN', 'name' => 'چین', 'name_english' => 'China'],
        ['code' => 'CX', 'name' => 'جزیره کریسمس', 'name_english' => 'Christmas Island'],
        ['code' => 'CC', 'name' => 'جزایر کوکوس', 'name_english' => 'Cocos (Keeling) Islands'],
        ['code' => 'CO', 'name' => 'کلمبیا', 'name_english' => 'Colombia'],
        ['code' => 'KM', 'name' => 'کومور', 'name_english' => 'Comoros'],
        ['code' => 'CD', 'name' => 'کنگو (جمهوری دموکراتیک)', 'name_english' => 'Congo (Democratic Republic of the)'],
        ['code' => 'CG', 'name' => 'کنگو', 'name_english' => 'Congo'],
        ['code' => 'CK', 'name' => 'جزایر کوک', 'name_english' => 'Cook Islands'],
        ['code' => 'CR', 'name' => 'کاستاریکا', 'name_english' => 'Costa Rica'],
        ['code' => 'CI', 'name' => 'ساحل عاج', 'name_english' => 'Côte d\'Ivoire'],
        ['code' => 'HR', 'name' => 'کرواسی', 'name_english' => 'Croatia'],
        ['code' => 'CU', 'name' => 'کوبا', 'name_english' => 'Cuba'],
        ['code' => 'CW', 'name' => 'کوراسائو', 'name_english' => 'Curaçao'],
        ['code' => 'CY', 'name' => 'قبرس', 'name_english' => 'Cyprus'],
        ['code' => 'CZ', 'name' => 'چکیا', 'name_english' => 'Czechia'],
        ['code' => 'DK', 'name' => 'دانمارک', 'name_english' => 'Denmark'],
        ['code' => 'DJ', 'name' => 'جیبوتی', 'name_english' => 'Djibouti'],
        ['code' => 'DM', 'name' => 'دومینیکا', 'name_english' => 'Dominica'],
        ['code' => 'DO', 'name' => 'جمهوری دومینیکن', 'name_english' => 'Dominican Republic'],
        ['code' => 'EC', 'name' => 'اکوادور', 'name_english' => 'Ecuador'],
        ['code' => 'EG', 'name' => 'مصر', 'name_english' => 'Egypt'],
        ['code' => 'SV', 'name' => 'السالوادور', 'name_english' => 'El Salvador'],
        ['code' => 'GQ', 'name' => 'گینه استوایی', 'name_english' => 'Equatorial Guinea'],
        ['code' => 'ER', 'name' => 'اریتره', 'name_english' => 'Eritrea'],
        ['code' => 'EE', 'name' => 'استونی', 'name_english' => 'Estonia'],
        ['code' => 'ET', 'name' => 'اتیوپی', 'name_english' => 'Ethiopia'],
        ['code' => 'FK', 'name' => 'جزایر فالکلند', 'name_english' => 'Falkland Islands (Malvinas)'],
        ['code' => 'FO', 'name' => 'جزایر فارو', 'name_english' => 'Faroe Islands'],
        ['code' => 'FJ', 'name' => 'فیجی', 'name_english' => 'Fiji'],
        ['code' => 'FI', 'name' => 'فنلاند', 'name_english' => 'Finland'],
        ['code' => 'FR', 'name' => 'فرانسه', 'name_english' => 'France'],
        ['code' => 'GF', 'name' => 'گویان فرانسه', 'name_english' => 'French Guiana'],
        ['code' => 'PF', 'name' => 'پلی‌نزی فرانسه', 'name_english' => 'French Polynesia'],
        ['code' => 'TF', 'name' => 'سرزمین‌های جنوبی فرانسه', 'name_english' => 'French Southern Territories'],
        ['code' => 'GA', 'name' => 'گابن', 'name_english' => 'Gabon'],
        ['code' => 'GM', 'name' => 'گامبیا', 'name_english' => 'Gambia'],
        ['code' => 'GE', 'name' => 'گرجستان', 'name_english' => 'Georgia'],
        ['code' => 'DE', 'name' => 'آلمان', 'name_english' => 'Germany'],
        ['code' => 'GH', 'name' => 'غنا', 'name_english' => 'Ghana'],
        ['code' => 'GI', 'name' => 'جبل‌الطارق', 'name_english' => 'Gibraltar'],
        ['code' => 'GR', 'name' => 'یونان', 'name_english' => 'Greece'],
        ['code' => 'GL', 'name' => 'گرینلند', 'name_english' => 'Greenland'],
        ['code' => 'GD', 'name' => 'گرنادا', 'name_english' => 'Grenada'],
        ['code' => 'GP', 'name' => 'گوادلوپ', 'name_english' => 'Guadeloupe'],
        ['code' => 'GU', 'name' => 'گوام', 'name_english' => 'Guam'],
        ['code' => 'GT', 'name' => 'گواتمالا', 'name_english' => 'Guatemala'],
        ['code' => 'GG', 'name' => 'گرنزی', 'name_english' => 'Guernsey'],
        ['code' => 'GN', 'name' => 'گینه', 'name_english' => 'Guinea'],
        ['code' => 'GW', 'name' => 'گینه بیسائو', 'name_english' => 'Guinea-Bissau'],
        ['code' => 'GY', 'name' => 'گویان', 'name_english' => 'Guyana'],
        ['code' => 'HT', 'name' => 'هائیتی', 'name_english' => 'Haiti'],
        ['code' => 'HM', 'name' => 'جزیره هرد و جزایر مک‌دونالد', 'name_english' => 'Heard Island and McDonald Islands'],
        ['code' => 'VA', 'name' => 'سریر مقدس', 'name_english' => 'Holy See'],
        ['code' => 'HN', 'name' => 'هندوراس', 'name_english' => 'Honduras'],
        ['code' => 'HK', 'name' => 'هنگ کنگ', 'name_english' => 'Hong Kong'],
        ['code' => 'HU', 'name' => 'مجارستان', 'name_english' => 'Hungary'],
        ['code' => 'IS', 'name' => 'ایسلند', 'name_english' => 'Iceland'],
        ['code' => 'IN', 'name' => 'هند', 'name_english' => 'India'],
        ['code' => 'ID', 'name' => 'اندونزی', 'name_english' => 'Indonesia'],
        ['code' => 'IR', 'name' => 'ایران', 'name_english' => 'Iran (Islamic Republic of)'],
        ['code' => 'IQ', 'name' => 'عراق', 'name_english' => 'Iraq'],
        ['code' => 'IE', 'name' => 'ایرلند', 'name_english' => 'Ireland'],
        ['code' => 'IM', 'name' => 'جزیره من', 'name_english' => 'Isle of Man'],
        ['code' => 'IL', 'name' => 'اسرائیل', 'name_english' => 'Israel'],
        ['code' => 'IT', 'name' => 'ایتالیا', 'name_english' => 'Italy'],
        ['code' => 'JM', 'name' => 'جامائیکا', 'name_english' => 'Jamaica'],
        ['code' => 'JP', 'name' => 'ژاپن', 'name_english' => 'Japan'],
        ['code' => 'JE', 'name' => 'جرزی', 'name_english' => 'Jersey'],
        ['code' => 'JO', 'name' => 'اردن', 'name_english' => 'Jordan'],
        ['code' => 'KZ', 'name' => 'قزاقستان', 'name_english' => 'Kazakhstan'],
        ['code' => 'KE', 'name' => 'کنیا', 'name_english' => 'Kenya'],
        ['code' => 'KI', 'name' => 'کیریباتی', 'name_english' => 'Kiribati'],
        ['code' => 'KP', 'name' => 'کره شمالی', 'name_english' => 'Korea (Democratic People\'s Republic of)'],
        ['code' => 'KR', 'name' => 'کره جنوبی', 'name_english' => 'Korea, Republic of'],
        ['code' => 'KW', 'name' => 'کویت', 'name_english' => 'Kuwait'],
        ['code' => 'KG', 'name' => 'قرقیزستان', 'name_english' => 'Kyrgyzstan'],
        ['code' => 'LA', 'name' => 'لائوس', 'name_english' => 'Lao People\'s Democratic Republic'],
        ['code' => 'LV', 'name' => 'لتونی', 'name_english' => 'Latvia'],
        ['code' => 'LB', 'name' => 'لبنان', 'name_english' => 'Lebanon'],
        ['code' => 'LS', 'name' => 'لسوتو', 'name_english' => 'Lesotho'],
        ['code' => 'LR', 'name' => 'لیبریا', 'name_english' => 'Liberia'],
        ['code' => 'LY', 'name' => 'لیبی', 'name_english' => 'Libya'],
        ['code' => 'LI', 'name' => 'لیختن‌اشتاین', 'name_english' => 'Liechtenstein'],
        ['code' => 'LT', 'name' => 'لیتوانی', 'name_english' => 'Lithuania'],
        ['code' => 'LU', 'name' => 'لوکزامبورگ', 'name_english' => 'Luxembourg'],
        ['code' => 'MO', 'name' => 'ماکائو', 'name_english' => 'Macao'],
        ['code' => 'MG', 'name' => 'ماداگاسکار', 'name_english' => 'Madagascar'],
        ['code' => 'MW', 'name' => 'مالاوی', 'name_english' => 'Malawi'],
        ['code' => 'MY', 'name' => 'مالزی', 'name_english' => 'Malaysia'],
        ['code' => 'MV', 'name' => 'مالدیو', 'name_english' => 'Maldives'],
        ['code' => 'ML', 'name' => 'مالی', 'name_english' => 'Mali'],
        ['code' => 'MT', 'name' => 'مالت', 'name_english' => 'Malta'],
        ['code' => 'MH', 'name' => 'جزایر مارشال', 'name_english' => 'Marshall Islands'],
        ['code' => 'MQ', 'name' => 'مارتینیک', 'name_english' => 'Martinique'],
        ['code' => 'MR', 'name' => 'موریتانی', 'name_english' => 'Mauritania'],
        ['code' => 'MU', 'name' => 'موریس', 'name_english' => 'Mauritius'],
        ['code' => 'YT', 'name' => 'مایوت', 'name_english' => 'Mayotte'],
        ['code' => 'MX', 'name' => 'مکزیک', 'name_english' => 'Mexico'],
        ['code' => 'FM', 'name' => 'میکرونزی', 'name_english' => 'Micronesia (Federated States of)'],
        ['code' => 'MD', 'name' => 'مولداوی', 'name_english' => 'Moldova, Republic of'],
        ['code' => 'MC', 'name' => 'موناکو', 'name_english' => 'Monaco'],
        ['code' => 'MN', 'name' => 'مغولستان', 'name_english' => 'Mongolia'],
        ['code' => 'ME', 'name' => 'مونته‌نگرو', 'name_english' => 'Montenegro'],
        ['code' => 'MS', 'name' => 'مونتسرات', 'name_english' => 'Montserrat'],
        ['code' => 'MA', 'name' => 'مراکش', 'name_english' => 'Morocco'],
        ['code' => 'MZ', 'name' => 'موزامبیک', 'name_english' => 'Mozambique'],
        ['code' => 'MM', 'name' => 'میانمار', 'name_english' => 'Myanmar'],
        ['code' => 'NA', 'name' => 'نامیبیا', 'name_english' => 'Namibia'],
        ['code' => 'NR', 'name' => 'نائورو', 'name_english' => 'Nauru'],
        ['code' => 'NP', 'name' => 'نپال', 'name_english' => 'Nepal'],
        ['code' => 'NL', 'name' => 'هلند', 'name_english' => 'Netherlands'],
        ['code' => 'NC', 'name' => 'کالدونیای جدید', 'name_english' => 'New Caledonia'],
        ['code' => 'NZ', 'name' => 'نیوزیلند', 'name_english' => 'New Zealand'],
        ['code' => 'NI', 'name' => 'نیکاراگوئه', 'name_english' => 'Nicaragua'],
        ['code' => 'NE', 'name' => 'نیجر', 'name_english' => 'Niger'],
        ['code' => 'NG', 'name' => 'نیجریه', 'name_english' => 'Nigeria'],
        ['code' => 'NU', 'name' => 'نیووی', 'name_english' => 'Niue'],
        ['code' => 'NF', 'name' => 'جزیره نورفولک', 'name_english' => 'Norfolk Island'],
        ['code' => 'MK', 'name' => 'مقدونیه شمالی', 'name_english' => 'North Macedonia'],
        ['code' => 'MP', 'name' => 'جزایر ماریانای شمالی', 'name_english' => 'Northern Mariana Islands'],
        ['code' => 'NO', 'name' => 'نروژ', 'name_english' => 'Norway'],
        ['code' => 'OM', 'name' => 'عمان', 'name_english' => 'Oman'],
        ['code' => 'PK', 'name' => 'پاکستان', 'name_english' => 'Pakistan'],
        ['code' => 'PW', 'name' => 'پالائو', 'name_english' => 'Palau'],
        ['code' => 'PS', 'name' => 'فلسطین', 'name_english' => 'Palestine, State of'],
        ['code' => 'PA', 'name' => 'پاناما', 'name_english' => 'Panama'],
        ['code' => 'PG', 'name' => 'پاپوآ گینه نو', 'name_english' => 'Papua New Guinea'],
        ['code' => 'PY', 'name' => 'پاراگوئه', 'name_english' => 'Paraguay'],
        ['code' => 'PE', 'name' => 'پرو', 'name_english' => 'Peru'],
        ['code' => 'PH', 'name' => 'فیلیپین', 'name_english' => 'Philippines'],
        ['code' => 'PN', 'name' => 'پیت‌کرن', 'name_english' => 'Pitcairn'],
        ['code' => 'PL', 'name' => 'لهستان', 'name_english' => 'Poland'],
        ['code' => 'PT', 'name' => 'پرتغال', 'name_english' => 'Portugal'],
        ['code' => 'PR', 'name' => 'پورتوریکو', 'name_english' => 'Puerto Rico'],
        ['code' => 'QA', 'name' => 'قطر', 'name_english' => 'Qatar'],
        ['code' => 'RE', 'name' => 'رئونیون', 'name_english' => 'Réunion'],
        ['code' => 'RO', 'name' => 'رومانی', 'name_english' => 'Romania'],
        ['code' => 'RU', 'name' => 'روسیه', 'name_english' => 'Russian Federation'],
        ['code' => 'RW', 'name' => 'رواندا', 'name_english' => 'Rwanda'],
        ['code' => 'BL', 'name' => 'سنت بارتلمی', 'name_english' => 'Saint Barthélemy'],
        ['code' => 'SH', 'name' => 'سنت هلنا، آسونسیون و تریستان دا کونا', 'name_english' => 'Saint Helena, Ascension and Tristan da Cunha'],
        ['code' => 'KN', 'name' => 'سنت کیتس و نویس', 'name_english' => 'Saint Kitts and Nevis'],
        ['code' => 'LC', 'name' => 'سنت لوسیا', 'name_english' => 'Saint Lucia'],
        ['code' => 'MF', 'name' => 'سنت مارتین (بخش فرانسه)', 'name_english' => 'Saint Martin (French part)'],
        ['code' => 'PM', 'name' => 'سنت پیر و میکلون', 'name_english' => 'Saint Pierre and Miquelon'],
        ['code' => 'VC', 'name' => 'سنت وینسنت و گرنادین‌ها', 'name_english' => 'Saint Vincent and the Grenadines'],
        ['code' => 'WS', 'name' => 'ساموآ', 'name_english' => 'Samoa'],
        ['code' => 'SM', 'name' => 'سان مارینو', 'name_english' => 'San Marino'],
        ['code' => 'ST', 'name' => 'سائوتومه و پرینسیپ', 'name_english' => 'Sao Tome and Principe'],
        ['code' => 'SA', 'name' => 'عربستان سعودی', 'name_english' => 'Saudi Arabia'],
        ['code' => 'SN', 'name' => 'سنگال', 'name_english' => 'Senegal'],
        ['code' => 'RS', 'name' => 'صربستان', 'name_english' => 'Serbia'],
        ['code' => 'SC', 'name' => 'سیشل', 'name_english' => 'Seychelles'],
        ['code' => 'SL', 'name' => 'سیرالئون', 'name_english' => 'Sierra Leone'],
        ['code' => 'SG', 'name' => 'سنگاپور', 'name_english' => 'Singapore'],
        ['code' => 'SX', 'name' => 'سینت مارتن (بخش هلند)', 'name_english' => 'Sint Maarten (Dutch part)'],
        ['code' => 'SK', 'name' => 'اسلواکی', 'name_english' => 'Slovakia'],
        ['code' => 'SI', 'name' => 'اسلوونی', 'name_english' => 'Slovenia'],
        ['code' => 'SB', 'name' => 'جزایر سلیمان', 'name_english' => 'Solomon Islands'],
        ['code' => 'SO', 'name' => 'سومالی', 'name_english' => 'Somalia'],
        ['code' => 'ZA', 'name' => 'آفریقای جنوبی', 'name_english' => 'South Africa'],
        ['code' => 'GS', 'name' => 'جورجیای جنوبی و جزایر ساندویچ جنوبی', 'name_english' => 'South Georgia and the South Sandwich Islands'],
        ['code' => 'SS', 'name' => 'سودان جنوبی', 'name_english' => 'South Sudan'],
        ['code' => 'ES', 'name' => 'اسپانیا', 'name_english' => 'Spain'],
        ['code' => 'LK', 'name' => 'سری‌لانکا', 'name_english' => 'Sri Lanka'],
        ['code' => 'SD', 'name' => 'سودان', 'name_english' => 'Sudan'],
        ['code' => 'SR', 'name' => 'سورینام', 'name_english' => 'Suriname'],
        ['code' => 'SJ', 'name' => 'سوالبارد و یان ماین', 'name_english' => 'Svalbard and Jan Mayen'],
        ['code' => 'SE', 'name' => 'سوئد', 'name_english' => 'Sweden'],
        ['code' => 'CH', 'name' => 'سوئیس', 'name_english' => 'Switzerland'],
        ['code' => 'SY', 'name' => 'سوریه', 'name_english' => 'Syrian Arab Republic'],
        ['code' => 'TW', 'name' => 'تایوان', 'name_english' => 'Taiwan, Province of China'],
        ['code' => 'TJ', 'name' => 'تاجیکستان', 'name_english' => 'Tajikistan'],
        ['code' => 'TZ', 'name' => 'تانزانیا', 'name_english' => 'Tanzania, United Republic of'],
        ['code' => 'TH', 'name' => 'تایلند', 'name_english' => 'Thailand'],
        ['code' => 'TL', 'name' => 'تیمور شرقی', 'name_english' => 'Timor-Leste'],
        ['code' => 'TG', 'name' => 'توگو', 'name_english' => 'Togo'],
        ['code' => 'TK', 'name' => 'توکلائو', 'name_english' => 'Tokelau'],
        ['code' => 'TO', 'name' => 'تونگا', 'name_english' => 'Tonga'],
        ['code' => 'TT', 'name' => 'ترینیداد و توباگو', 'name_english' => 'Trinidad and Tobago'],
        ['code' => 'TN', 'name' => 'تونس', 'name_english' => 'Tunisia'],
        ['code' => 'TR', 'name' => 'ترکیه', 'name_english' => 'Turkey'],
        ['code' => 'TM', 'name' => 'ترکمنستان', 'name_english' => 'Turkmenistan'],
        ['code' => 'TC', 'name' => 'جزایر ترکس و کایکوس', 'name_english' => 'Turks and Caicos Islands'],
        ['code' => 'TV', 'name' => 'تووالو', 'name_english' => 'Tuvalu'],
        ['code' => 'UG', 'name' => 'اوگاندا', 'name_english' => 'Uganda'],
        ['code' => 'UA', 'name' => 'اوکراین', 'name_english' => 'Ukraine'],
        ['code' => 'AE', 'name' => 'امارات متحده عربی', 'name_english' => 'United Arab Emirates'],
        ['code' => 'GB', 'name' => 'بریتانیا', 'name_english' => 'United Kingdom'],
        ['code' => 'US', 'name' => 'ایالات متحده آمریکا', 'name_english' => 'United States of America'],
        ['code' => 'UM', 'name' => 'جزایر کوچک دورافتاده ایالات متحده', 'name_english' => 'United States Minor Outlying Islands'],
        ['code' => 'UY', 'name' => 'اروگوئه', 'name_english' => 'Uruguay'],
        ['code' => 'UZ', 'name' => 'ازبکستان', 'name_english' => 'Uzbekistan'],
        ['code' => 'VU', 'name' => 'وانواتو', 'name_english' => 'Vanuatu'],
        ['code' => 'VE', 'name' => 'ونزوئلا', 'name_english' => 'Venezuela (Bolivarian Republic of)'],
        ['code' => 'VN', 'name' => 'ویتنام', 'name_english' => 'Viet Nam'],
        ['code' => 'VG', 'name' => 'جزایر ویرجین (بریتانیا)', 'name_english' => 'Virgin Islands (British)'],
        ['code' => 'VI', 'name' => 'جزایر ویرجین (ایالات متحده)', 'name_english' => 'Virgin Islands (U.S.)'],
        ['code' => 'WF', 'name' => 'والیس و فوتونا', 'name_english' => 'Wallis and Futuna'],
        ['code' => 'EH', 'name' => 'صحرا غربی', 'name_english' => 'Western Sahara'],
        ['code' => 'YE', 'name' => 'یمن', 'name_english' => 'Yemen'],
        ['code' => 'ZM', 'name' => 'زامبیا', 'name_english' => 'Zambia'],
        ['code' => 'ZW', 'name' => 'زیمبابوه', 'name_english' => 'Zimbabwe'],
    ];

    /**
     * @param  string  $code  The 2-letter ISO country code.
     * @return string|null The localized country name, or English name, or null if not found.
     */
    public function getCountryNameByCode(string $code): ?string
    {
        $code = strtoupper($code);
        $locale = App::getLocale();

        if (! isset($this->nameIndexByLocale[$locale])) {
            $this->buildLocaleCache($locale);
        }

        return $this->nameIndexByLocale[$locale][$code] ?? null;
    }

    /**
     * @return array An associative array where key is ISO code and value is localized name.
     */
    public function getCountriesList(): array
    {
        $locale = App::getLocale();

        if (! isset($this->sortedListByLocale[$locale])) {
            $this->buildLocaleCache($locale);
        }

        return $this->sortedListByLocale[$locale];
    }

    /**
     * @param  string  $locale  The locale for which to build the cache.
     */
    private function buildLocaleCache(string $locale): void
    {
        $nameKey = ($locale === 'fa') ? 'name' : 'name_english';
        $nameIndex = [];
        $countriesList = [];

        foreach ($this->countries as $country) {
            $code = $country['code'];
            // Prioritize localized name, then English name as fallback for direct lookup
            $nameIndex[$code] = $country[$nameKey] ?? $country['name_english'] ?? null;
            // For the sorted list, also ensure a fallback
            $countriesList[$code] = $country[$nameKey] ?? $country['name_english'] ?? null;
        }

        asort($countriesList);

        $this->nameIndexByLocale[$locale] = $nameIndex;
        $this->sortedListByLocale[$locale] = $countriesList;
    }
}
