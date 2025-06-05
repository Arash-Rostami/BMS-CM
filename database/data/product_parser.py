import pandas as pd
import json
import re
import unicodedata
from pathlib import Path

categories = [
    {'id': '1', 'name': 'محصولات سلولزی', 'slug': 'cellulosic-products', 'english_name': 'Cellulosic products', 'description': None, 'parent_id': None, 'level': '0', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 02:41:11', 'updated_at': '2025-05-16 03:01:59'},
    {'id': '2', 'name': 'محصولات شیمیایی و پلیمری', 'slug': 'chemical-polymer-products', 'english_name': 'Chemical polymer products', 'description': None, 'parent_id': None, 'level': '0', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 02:41:11', 'updated_at': '2025-05-16 03:01:59'},
    {'id': '3', 'name': 'محصولات چوبی', 'slug': 'wood-products', 'english_name': 'Wood products', 'description': None, 'parent_id': None, 'level': '0', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 02:41:11', 'updated_at': '2025-05-16 03:01:59'},
    {'id': '4', 'name': 'خمیرها', 'slug': 'pulps', 'english_name': 'Pulps', 'description': None, 'parent_id': '1', 'level': '1', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 02:44:44', 'updated_at': '2025-05-16 03:01:59'},
    {'id': '5', 'name': 'کاغذ و مقوا', 'slug': 'paper-boards', 'english_name': 'Paper boards', 'description': None, 'parent_id': '1', 'level': '1', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 02:44:44', 'updated_at': '2025-05-16 03:01:59'},
    {'id': '6', 'name': 'محصولات شیمیایی', 'slug': 'chemical-products', 'english_name': 'Chemical products', 'description': None, 'parent_id': '2', 'level': '1', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 02:47:03', 'updated_at': '2025-05-16 03:01:59'},
    {'id': '7', 'name': 'محصولات پلیمری', 'slug': 'polymer-products', 'english_name': 'Polymer products', 'description': None, 'parent_id': '2', 'level': '1', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 02:47:03', 'updated_at': '2025-05-16 03:01:59'},
    {'id': '8', 'name': 'چوب اره شده', 'slug': 'sawn-wood', 'english_name': 'Sawn wood', 'description': None, 'parent_id': '3', 'level': '1', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 02:52:49', 'updated_at': '2025-05-16 03:01:59'},
    {'id': '9', 'name': 'ترموود', 'slug': 'themowood', 'english_name': 'Themowood', 'description': None, 'parent_id': '3', 'level': '1', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 02:52:49', 'updated_at': '2025-05-16 03:01:59'},
    {'id': '10', 'name': 'تخته چندلا', 'slug': 'plywood', 'english_name': 'Plywood', 'description': None, 'parent_id': '3', 'level': '1', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 02:52:49', 'updated_at': '2025-05-16 03:01:59'},
    {'id': '11', 'name': 'چوب راش', 'slug': 'beech', 'english_name': 'Beech', 'description': None, 'parent_id': '3', 'level': '1', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 02:52:49', 'updated_at': '2025-05-16 03:01:59'},
    {'id': '12', 'name': 'سایر محصولات چوبی', 'slug': 'other-woods', 'english_name': 'Other woods', 'description': None, 'parent_id': '3', 'level': '1', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 02:52:49', 'updated_at': '2025-05-16 03:01:59'},
    {'id': '13', 'name': 'سایر مواد شیمیایی', 'slug': 'other-chemicals', 'english_name': 'Other chemicals', 'description': None, 'parent_id': '2', 'level': '1', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 02:57:39', 'updated_at': '2025-05-16 03:01:59'},
    {'id': '14', 'name': 'لاینر اندود شده سفید', 'slug': 'coated-white-top-liner-cwtl', 'english_name': 'Coated white top liner cwtl', 'description': None, 'parent_id': '5', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:06:05', 'updated_at': '2025-05-16 03:06:05'},
    {'id': '15', 'name': 'کاغذ گلاسه براق', 'slug': 'glossy-coated-art-paper', 'english_name': 'Glossy coated art paper', 'description': None, 'parent_id': '5', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:06:05', 'updated_at': '2025-05-16 03:06:05'},
    {'id': '16', 'name': 'مقوای جعبه تاشو', 'slug': 'folding-box-board-fbb', 'english_name': 'Folding box board fbb', 'description': None, 'parent_id': '5', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:06:05', 'updated_at': '2025-05-16 03:06:05'},
    {'id': '17', 'name': 'کاغذ کرافت برای پاکت', 'slug': 'sack-kraft', 'english_name': 'Sack kraft', 'description': None, 'parent_id': '5', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:06:05', 'updated_at': '2025-05-16 03:06:05'},
    {'id': '18', 'name': 'کاغذ لاینر کرافت', 'slug': 'kraft-liner', 'english_name': 'Kraft liner', 'description': None, 'parent_id': '5', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:06:05', 'updated_at': '2025-05-16 03:06:05'},
    {'id': '19', 'name': 'کاغذ گلاسه مات', 'slug': 'matte-coated-art-paper', 'english_name': 'Matte coated art paper', 'description': None, 'parent_id': '5', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:06:05', 'updated_at': '2025-05-16 03:06:05'},
    {'id': '20', 'name': 'مقوای پشت طوسی', 'slug': 'greyback', 'english_name': 'Greyback', 'description': None, 'parent_id': '5', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:06:05', 'updated_at': '2025-05-16 03:06:05'},
    {'id': '21', 'name': 'کاغذ بدون چوب', 'slug': 'wood-free-paper', 'english_name': 'Wood free paper', 'description': None, 'parent_id': '5', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:06:05', 'updated_at': '2025-05-16 03:06:05'},
    {'id': '22', 'name': 'خمیر الیاف بلند سفید شده', 'slug': 'bleached-softwood-pulp', 'english_name': 'Bleached softwood pulp', 'description': None, 'parent_id': '4', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:07:40', 'updated_at': '2025-05-16 03:07:40'},
    {'id': '23', 'name': 'خمیر الیاف کوتاه سفید شده', 'slug': 'bleached-hardwood-pulp', 'english_name': 'Bleached hardwood pulp', 'description': None, 'parent_id': '4', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:07:40', 'updated_at': '2025-05-16 03:07:40'},
    {'id': '24', 'name': 'خمیر الیاف بلند سفید نشده', 'slug': 'unbleached-softwood-pulp', 'english_name': 'Unbleached softwood pulp', 'description': None, 'parent_id': '4', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:07:40', 'updated_at': '2025-05-16 03:07:40'},
    {'id': '25', 'name': 'اسید فسفریک', 'slug': 'phosphoric-acid', 'english_name': 'Phosphoric acid', 'description': None, 'parent_id': '6', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:09:17', 'updated_at': '2025-05-16 03:09:17'},
    {'id': '26', 'name': 'سدیم متابی سولفیت', 'slug': 'sodium-metabisulfite', 'english_name': 'Sodium metabisulfite', 'description': None, 'parent_id': '6', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:09:17', 'updated_at': '2025-05-16 03:09:17'},
    {'id': '27', 'name': 'اسید اسکوربیک', 'slug': 'ascorbic-acid', 'english_name': 'Ascorbic acid', 'description': None, 'parent_id': '6', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:09:17', 'updated_at': '2025-05-16 03:09:17'},
    {'id': '28', 'name': 'پتاسیم هیدروکسید', 'slug': 'potassium-hydroxide', 'english_name': 'Potassium hydroxide', 'description': None, 'parent_id': '6', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:09:17', 'updated_at': '2025-05-16 03:09:17'},
    {'id': '29', 'name': 'فوق جاذب', 'slug': 'super-absorbant', 'english_name': 'Super absorbant', 'description': None, 'parent_id': '6', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:09:17', 'updated_at': '2025-05-16 03:09:17'},
    {'id': '30', 'name': 'سیترات', 'slug': 'citrate', 'english_name': 'Citrate', 'description': None, 'parent_id': '6', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:09:17', 'updated_at': '2025-05-16 03:09:17'},
    {'id': '31', 'name': 'کربنات پتاسیم', 'slug': 'potassium-carbonate', 'english_name': 'Potassium carbonate', 'description': None, 'parent_id': '6', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:09:17', 'updated_at': '2025-05-16 03:09:17'},
    {'id': '32', 'name': 'نیتریت سدیم', 'slug': 'sodium-nitrite', 'english_name': 'Sodium nitrite', 'description': None, 'parent_id': '6', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:09:17', 'updated_at': '2025-05-16 03:09:17'},
    {'id': '33', 'name': 'اسید آدیپیک', 'slug': 'adipic-acid', 'english_name': 'Adipic acid', 'description': None, 'parent_id': '6', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:09:17', 'updated_at': '2025-05-16 03:09:17'},
    {'id': '34', 'name': 'کربنات پتاسیم UNID', 'slug': 'potassium-carbonate-unid', 'english_name': 'Potassium carbonate unid', 'description': None, 'parent_id': '6', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:09:17', 'updated_at': '2025-05-16 03:09:17'},
    {'id': '35', 'name': 'پتاسیم هیدروکسید UNID', 'slug': 'potassium-hydroxide-unid', 'english_name': 'Potassium hydroxide unid', 'description': None, 'parent_id': '6', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:09:17', 'updated_at': '2025-05-16 03:09:17'},
    {'id': '36', 'name': 'پلی آمید', 'slug': 'polyamide', 'english_name': 'Polyamide', 'description': None, 'parent_id': '7', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:16:11', 'updated_at': '2025-05-16 03:16:11'},
    {'id': '37', 'name': 'الیاف شیشه پلی آمید', 'slug': 'polyamide-glass-fiber', 'english_name': 'Polyamide Glass Fiber', 'description': None, 'parent_id': '7', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:16:11', 'updated_at': '2025-05-16 03:16:11'},
    {'id': '38', 'name': 'پلی استال', 'slug': 'polyacetal', 'english_name': 'Polyacetal', 'description': None, 'parent_id': '7', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:16:11', 'updated_at': '2025-05-16 03:16:11'},
    {'id': '39', 'name': 'پلی کربنات', 'slug': 'polycarbonate', 'english_name': 'Polycarbonate', 'description': None, 'parent_id': '7', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:16:11', 'updated_at': '2025-05-16 03:16:11'},
    {'id': '40', 'name': 'الاستومر پلی الفین (POE)', 'slug': 'polyolefin-elastomer-poe', 'english_name': 'Polyolefin Elastomer POE', 'description': None, 'parent_id': '7', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:16:11', 'updated_at': '2025-05-16 03:16:11'},
    {'id': '41', 'name': 'پلی آمید 6 6', 'slug': 'poly-amide-6-6', 'english_name': 'Poly Amide 6 6', 'description': None, 'parent_id': '7', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:16:11', 'updated_at': '2025-05-16 03:16:11'},
    {'id': '42', 'name': 'الیاف شیشه PP', 'slug': 'glassfiber-pp', 'english_name': 'Glassfiber PP', 'description': None, 'parent_id': '7', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:16:11', 'updated_at': '2025-05-16 03:16:11'},
    {'id': '43', 'name': 'چوب اره شده خشک یولکا', 'slug': 'yulka-dry-sawn-wood', 'english_name': 'Yulka Dry Sawn Wood', 'description': None, 'parent_id': '8', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:19:28', 'updated_at': '2025-05-16 03:19:28'},
    {'id': '44', 'name': 'چوب اره شده خیس', 'slug': 'wet-sawn-wood', 'english_name': 'Wet Sawn Wood', 'description': None, 'parent_id': '8', 'level': '2', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:19:28', 'updated_at': '2025-05-16 03:19:28'},
    {'id': '45', 'name': 'جیان', 'slug': 'jian', 'english_name': 'Jian', 'description': None, 'parent_id': '14', 'level': '3', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:24:24', 'updated_at': '2025-05-16 03:24:24'},
    {'id': '46', 'name': 'لی و من', 'slug': 'lee-man', 'english_name': 'Lee Man', 'description': None, 'parent_id': '14', 'level': '3', 'active': '0', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:24:24', 'updated_at': '2025-05-16 03:24:24'},
    {'id': '47', 'name': 'وی', 'slug': 'v', 'english_name': 'V', 'description': None, 'parent_id': '14', 'level': '3', 'active': '0', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:24:24', 'updated_at': '2025-05-16 03:24:24'},
    {'id': '48', 'name': 'نینگبو', 'slug': 'ningbo', 'english_name': 'Ningbo', 'description': None, 'parent_id': '16', 'level': '3', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:26:09', 'updated_at': '2025-05-16 03:26:09'},
    {'id': '49', 'name': 'بوهویی', 'slug': 'bohui', 'english_name': 'Bohui', 'description': None, 'parent_id': '16', 'level': '3', 'active': '0', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:26:09', 'updated_at': '2025-05-16 03:26:09'},
    {'id': '50', 'name': 'اکسپلور', 'slug': 'xplore-glossy', 'english_name': 'Xplore Glossy', 'description': None, 'parent_id': '15', 'level': '3', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 03:27:02', 'updated_at': '2025-05-16 03:27:02'},
    {'id': '94', 'name': 'چن مینگ', 'slug': 'chenming-glossy', 'english_name': 'Chenming Glossy', 'description': None, 'parent_id': '15', 'level': '3', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 04:27:23', 'updated_at': '2025-05-16 04:27:23'},
    {'id': '95', 'name': 'یونگ تای', 'slug': 'yongtai', 'english_name': 'Yongtai', 'description': None, 'parent_id': '20', 'level': '3', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 04:35:40', 'updated_at': '2025-05-16 04:35:40'},
    {'id': '96', 'name': 'آرخانگلسک', 'slug': 'arkhanglesk', 'english_name': 'Arkhanglesk', 'description': None, 'parent_id': '18', 'level': '3', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 04:36:54', 'updated_at': '2025-05-16 04:36:54'},
    {'id': '97', 'name': 'چن مینگ', 'slug': 'chenming-matte', 'english_name': 'Chenming Matte', 'description': None, 'parent_id': '19', 'level': '3', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 04:38:39', 'updated_at': '2025-05-16 04:38:39'},
    {'id': '99', 'name': 'سژژا', 'slug': 'segezha', 'english_name': 'Segezha', 'description': None, 'parent_id': '17', 'level': '3', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 04:41:28', 'updated_at': '2025-05-16 04:41:28'},
    {'id': '100', 'name': 'چن مینگ', 'slug': 'chenming-wood-free', 'english_name': 'Chenming Wood Free', 'description': None, 'parent_id': '21', 'level': '3', 'active': '1', 'user_id': '1', 'updated_by_id': '1', 'deleted_at': None, 'created_at': '2025-05-16 04:43:13', 'updated_at': '2025-05-16 04:43:13'},
    {'id': '198', 'name': 'اکسپلور', 'slug': 'xplore-matte', 'english_name': 'Xplore Matte', 'description': None, 'parent_id': '19', 'level': '3', 'active': '1', 'user_id': '1', 'updated_by_id': None, 'deleted_at': None, 'created_at': '2025-05-16 18:03:03', 'updated_at': '2025-05-16 18:03:03'}
]

# Build lookup: slug → id
category_lookup = {cat['slug']: cat['id'] for cat in categories}

def slugify(text):
    text = unicodedata.normalize('NFKD', str(text))
    text = text.encode('ascii', 'ignore').decode('ascii').lower()
    text = re.sub(r'[\s_]+', '-', text)
    text = re.sub(r'[^a-z0-9\-]+', '', text)
    text = re.sub(r'-{2,}', '-', text).strip('-')
    return text

# Load & filter
df = pd.read_excel('ProductList.xlsx')
df = df[(df['Status'] == 1) & (df['G1'] != 'Other Products')].copy()

def parse_row(row):
    # split name
    segments   = [s.strip() for s in str(row['PartName']).split('-') if s.strip()]
    seg_slugs  = [slugify(s) for s in segments]
    # collect G1→G4
    cats = []
    for lvl in range(1,5):
        v = row.get(f'G{lvl}')
        if pd.notna(v):
            name = v.strip()
            cats.append((lvl, name, slugify(name)))
    # find deepest match
    matched_slug = None
    matched_lvl  = None
    for lvl, name, slug in reversed(cats):
        if any(s.startswith(slug) for s in seg_slugs):
            matched_slug = slug
            matched_lvl  = lvl
            break
    if matched_slug is None and cats:
        matched_lvl, _, matched_slug = cats[-1]
    # derive base category_table_slug
    category_slug = matched_slug
    category_table_slug = matched_slug

    # special suffix rules for Chenming and Xplore
    g3 = str(row.get('G3','')).lower()
    if 'chenming' in seg_slugs or 'chenming' in slugify(g3):
        # detect matte/glossy/wood in the entire PartName
        name_lower = row['PartName'].lower()
        if 'matte' in name_lower:
            category_table_slug = 'chenming-matte'
        elif 'glossy' in name_lower:
            category_table_slug = 'chenming-glossy'
        elif 'wood' in name_lower:
            category_table_slug = 'chenming-wood-free'
    elif 'xplore' in seg_slugs or 'xplore' in slugify(g3):
        name_lower = row['PartName'].lower()
        if 'matte'  in name_lower:
            category_table_slug = 'xplore-matte'
        elif 'glossy' in name_lower:
            category_table_slug = 'xplore-glossy'
        elif 'wood'   in name_lower:
            category_table_slug = 'xplore-wood-free'

    # lookup id
    category_id = category_lookup.get(category_table_slug)

    # remove used category slugs
    used = [slug for lvl,name,slug in cats if lvl <= (matched_lvl or 0)]
    # extras = those not starting with any used slug
    extras = [
        segments[i] for i,s in enumerate(seg_slugs)
        if not any(s.startswith(u) for u in used)
    ]

    return {
        'extra_tags_json': json.dumps(extras, ensure_ascii=False),
        'code':             row['PartCode'],
        'category_slug':    category_slug,
        'category_table_slug': category_table_slug,
        'category_id':      category_id
    }

parsed = df.apply(parse_row, axis=1, result_type='expand')
parsed.to_excel(Path('prepared_products.xlsx'), index=False)
print("Done: prepared_products.xlsx")
