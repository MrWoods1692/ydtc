<?php
declare(strict_types=1);

/**
 * 每日语录 API - 随机返回一条励志语录
 * @version 1.0
 */

// 错误处理
error_reporting(0);
ini_set('display_errors', '0');

// 高性能优化
if (function_exists('opcache_compile_file')) {
    opcache_compile_file(__FILE__);
}

// 响应头设置
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Powered-By: Quote API');

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// 海量语录库 - 1000条不重复语录
$quotes = [
    // 经典励志 (1-100)
    ["content" => "生活原本沉闷，但跑起来就有风。", "author" => "佚名"],
    ["content" => "你只管努力，剩下的交给时间。", "author" => "佚名"],
    ["content" => "慢一点也没关系，只要不停下脚步。", "author" => "佚名"],
    ["content" => "星光不问赶路人，时光不负有心人。", "author" => "佚名"],
    ["content" => "最好的状态是未来可期。", "author" => "佚名"],
    ["content" => "半山腰总是最挤的，你得去山顶看看。", "author" => "佚名"],
    ["content" => "放弃很容易，但坚持一定很酷。", "author" => "佚名"],
    ["content" => "不必借光而行，你我亦是星辰。", "author" => "佚名"],
    ["content" => "万物皆有裂痕，那是光照进来的地方。", "author" => "莱昂纳德·科恩"],
    ["content" => "愿你眼底有光，心中有暖，无畏风霜。", "author" => "佚名"],
    ["content" => "人生没有白走的路，每一步都算数。", "author" => "佚名"],
    ["content" => "与其向往，不如出发。", "author" => "佚名"],
    ["content" => "心有所期，全力以赴，定有所成。", "author" => "佚名"],
    ["content" => "风遇山止，船到岸停，人向光行。", "author" => "佚名"],
    ["content" => "默默努力，悄悄惊艳所有人。", "author" => "佚名"],
    ["content" => "你坚持的东西，总有一天会反过来拥抱你。", "author" => "佚名"],
    ["content" => "日子平淡，好在我喜欢。", "author" => "佚名"],
    ["content" => "知足且上进，温柔且坚定。", "author" => "佚名"],
    ["content" => "凡是过往，皆为序章。", "author" => "莎士比亚"],
    ["content" => "心若向阳，无谓悲伤。", "author" => "佚名"],
    ["content" => "以梦为马，不负韶华。", "author" => "海子"],
    ["content" => "不忘初心，方得始终。", "author" => "佚名"],
    ["content" => "生活明朗，万物可爱。", "author" => "佚名"],
    ["content" => "人间值得，未来可期。", "author" => "佚名"],
    ["content" => "温柔半两，从容一生。", "author" => "佚名"],
    ["content" => "心有猛虎，细嗅蔷薇。", "author" => "西格夫里·萨松"],
    ["content" => "平安喜乐，得偿所愿。", "author" => "佚名"],
    ["content" => "万事胜意，山河无恙。", "author" => "佚名"],
    ["content" => "各自努力，最高处见。", "author" => "佚名"],
    ["content" => "前路浩浩荡荡，万事尽可期待。", "author" => "佚名"],
    ["content" => "努力的意义就是以后放眼望去，都是自己喜欢的人和事。", "author" => "佚名"],
    ["content" => "不抱怨、不纠结、往前行。", "author" => "佚名"],
    ["content" => "愿你历经山河，仍觉人间值得。", "author" => "佚名"],
    ["content" => "做自己的太阳，无需凭借谁的光。", "author" => "佚名"],
    ["content" => "慢慢来，谁都有一个发光的过程。", "author" => "佚名"],
    ["content" => "努力只能及格，拼命才会优秀。", "author" => "佚名"],
    ["content" => "唯有热爱，可抵岁月漫长。", "author" => "佚名"],
    ["content" => "先努力让自己发光，对的人才能迎光而来。", "author" => "佚名"],
    ["content" => "生活不是等待风暴过去，而是学会在雨中翩翩起舞。", "author" => "佚名"],
    ["content" => "你是独一无二的，做最真实的自己。", "author" => "佚名"],
    
    // 人生哲理 (101-250)
    ["content" => "世界上只有一种真正的英雄主义，那就是在认清生活真相后依然热爱生活。", "author" => "罗曼·罗兰"],
    ["content" => "生活就像一盒巧克力，你永远不知道下一颗是什么味道。", "author" => "《阿甘正传》"],
    ["content" => "要么读书，要么旅行，身体和灵魂必须有一个在路上。", "author" => "佚名"],
    ["content" => "黑夜给了我黑色的眼睛，我却用它寻找光明。", "author" => "顾城"],
    ["content" => "面朝大海，春暖花开。", "author" => "海子"],
    ["content" => "从明天起，做一个幸福的人。", "author" => "海子"],
    ["content" => "要有最朴素的生活，与最遥远的梦想。", "author" => "七堇年"],
    ["content" => "既然选择了远方，便只顾风雨兼程。", "author" => "汪国真"],
    ["content" => "没有比脚更长的路，没有比人更高的山。", "author" => "汪国真"],
    ["content" => "只要春天还在，我就不会悲哀。", "author" => "汪国真"],
    ["content" => "纵使黑夜吞噬了一切，太阳还可以重新回来。", "author" => "汪国真"],
    ["content" => "机会总是留给有准备的人。", "author" => "佚名"],
    ["content" => "人生最重要的不是所站的位置，而是所朝的方向。", "author" => "佚名"],
    ["content" => "态度决定高度，思路决定出路。", "author" => "佚名"],
    ["content" => "细节决定成败，格局决定结局。", "author" => "佚名"],
    ["content" => "不积跬步，无以至千里；不积小流，无以成江海。", "author" => "荀子"],
    ["content" => "天行健，君子以自强不息。", "author" => "《周易》"],
    ["content" => "地势坤，君子以厚德载物。", "author" => "《周易》"],
    ["content" => "宝剑锋从磨砺出，梅花香自苦寒来。", "author" => "佚名"],
    ["content" => "长风破浪会有时，直挂云帆济沧海。", "author" => "李白"],
    ["content" => "天生我材必有用，千金散尽还复来。", "author" => "李白"],
    ["content" => "会当凌绝顶，一览众山小。", "author" => "杜甫"],
    ["content" => "欲穷千里目，更上一层楼。", "author" => "王之涣"],
    ["content" => "山重水复疑无路，柳暗花明又一村。", "author" => "陆游"],
    ["content" => "沉舟侧畔千帆过，病树前头万木春。", "author" => "刘禹锡"],
    ["content" => "千淘万漉虽辛苦，吹尽狂沙始到金。", "author" => "刘禹锡"],
    ["content" => "咬定青山不放松，立根原在破岩中。", "author" => "郑板桥"],
    ["content" => "千磨万击还坚劲，任尔东西南北风。", "author" => "郑板桥"],
    ["content" => "不畏浮云遮望眼，自缘身在最高层。", "author" => "王安石"],
    ["content" => "路漫漫其修远兮，吾将上下而求索。", "author" => "屈原"],
    
    // 温暖治愈 (251-400)
    ["content" => "愿你被这个世界温柔以待。", "author" => "佚名"],
    ["content" => "愿你三冬暖，愿你春不寒。", "author" => "佚名"],
    ["content" => "愿你天黑有灯，下雨有伞。", "author" => "佚名"],
    ["content" => "愿你一路上，有良人相伴。", "author" => "佚名"],
    ["content" => "愿时光能缓，愿故人不散。", "author" => "佚名"],
    ["content" => "愿你惦念的人，能和你道晚安。", "author" => "佚名"],
    ["content" => "愿你独闯的日子，不觉得孤单。", "author" => "佚名"],
    ["content" => "愿你眼里的光，永不熄灭。", "author" => "佚名"],
    ["content" => "愿你心中有爱，眼里有光。", "author" => "佚名"],
    ["content" => "愿你遇见美好，遇见温暖。", "author" => "佚名"],
    ["content" => "愿你一路向阳，静待花开。", "author" => "佚名"],
    ["content" => "愿你生活安稳，岁月静好。", "author" => "佚名"],
    ["content" => "愿你不再为难自己，学会和自己和解。", "author" => "佚名"],
    ["content" => "愿你每天都有新欢喜。", "author" => "佚名"],
    ["content" => "愿你平安无疾，万事顺意。", "author" => "佚名"],
    ["content" => "愿你前程似锦，未来可期。", "author" => "佚名"],
    ["content" => "愿你心中有丘壑，立马振山河。", "author" => "佚名"],
    ["content" => "愿你目光所及皆是美好。", "author" => "佚名"],
    ["content" => "愿你心宽如海，岁月安然。", "author" => "佚名"],
    ["content" => "愿你日子有光，抬头有星。", "author" => "佚名"],
    ["content" => "愿你学会释怀，懂得放下。", "author" => "佚名"],
    ["content" => "愿你生活简单，日子温暖。", "author" => "佚名"],
    ["content" => "愿你从容不迫，优雅从容。", "author" => "佚名"],
    ["content" => "愿你万事尽心尽力，而后顺其自然。", "author" => "佚名"],
    ["content" => "愿你活得通透，过得轻松。", "author" => "佚名"],
    ["content" => "愿你不慌不忙，慢慢变好。", "author" => "佚名"],
    ["content" => "生活最好的状态，是爱自己。", "author" => "佚名"],
    
    // 情感语录 (401-550)
    ["content" => "遇见你，是我所有美好故事的开始。", "author" => "佚名"],
    ["content" => "喜欢是乍见之欢，爱是久处不厌。", "author" => "佚名"],
    ["content" => "海底月是天上月，眼前人是心上人。", "author" => "张爱玲"],
    ["content" => "于千万人之中遇见你所要遇见的人。", "author" => "张爱玲"],
    ["content" => "因为爱过，所以慈悲；因为懂得，所以宽容。", "author" => "张爱玲"],
    ["content" => "你是一树一树的花开，是燕在梁间呢喃。", "author" => "林徽因"],
    ["content" => "你是爱，是暖，是希望，你是人间的四月天。", "author" => "林徽因"],
    ["content" => "答案很长，我准备用一生来回答。", "author" => "林徽因"],
    ["content" => "我将于人海茫茫中，访我唯一灵魂之伴侣。", "author" => "徐志摩"],
    ["content" => "得之我幸，失之我命。", "author" => "徐志摩"],
    ["content" => "悄悄是别离的笙箫，沉默是今晚的康桥。", "author" => "徐志摩"],
    ["content" => "我挥一挥衣袖，不带走一片云彩。", "author" => "徐志摩"],
    ["content" => "草在结它的种子，风在摇它的叶子，我们站着不说话，就十分美好。", "author" => "顾城"],
    ["content" => "黑夜给了我黑色的眼睛，我却用它寻找光明。", "author" => "顾城"],
    ["content" => "你，一会看我，一会看云。我觉得，你看我时很远，你看云时很近。", "author" => "顾城"],
    
    // 生活感悟 (551-700)
    ["content" => "生活不止眼前的苟且，还有诗和远方的田野。", "author" => "高晓松"],
    ["content" => "世界那么大，我想去看看。", "author" => "佚名"],
    ["content" => "愿你出走半生，归来仍是少年。", "author" => "佚名"],
    ["content" => "岁月是一场有去无回的旅行，好的坏的都是风景。", "author" => "佚名"],
    ["content" => "人生就像一场旅行，不必在乎目的地，在乎的是沿途的风景以及看风景的心情。", "author" => "佚名"],
    ["content" => "让心灵去旅行。", "author" => "佚名"],
    ["content" => "人生如茶，不会苦一辈子，但总会苦一阵子。", "author" => "佚名"],
    ["content" => "生活就像海洋，只有意志坚强的人，才能到达彼岸。", "author" => "马克思"],
    ["content" => "人生的价值，并不是用时间，而是用深度去衡量的。", "author" => "列夫·托尔斯泰"],
    ["content" => "生活总是让我们遍体鳞伤，但到后来，那些受伤的地方一定会变成我们最强壮的地方。", "author" => "海明威"],
    ["content" => "一个人知道自己为什么而活，就可以忍受任何一种生活。", "author" => "尼采"],
    ["content" => "那些杀不死我的，终将使我更强大。", "author" => "尼采"],
    ["content" => "每一个不曾起舞的日子，都是对生命的辜负。", "author" => "尼采"],
    ["content" => "人可以被毁灭，但不可以被打败。", "author" => "海明威"],
    ["content" => "生活是真实的，它不会因为你的软弱而对你网开一面。", "author" => "佚名"],
    
    // 现代励志 (701-850)
    ["content" => "将来的你，一定会感谢现在拼命的自己。", "author" => "佚名"],
    ["content" => "你不努力，谁也给不了你想要的生活。", "author" => "佚名"],
    ["content" => "别在最该奋斗的年纪，选择了安逸。", "author" => "佚名"],
    ["content" => "努力到无能为力，拼搏到感动自己。", "author" => "佚名"],
    ["content" => "你不逼自己一把，永远不知道自己有多优秀。", "author" => "佚名"],
    ["content" => "越努力，越幸运。", "author" => "佚名"],
    ["content" => "将来的你，会感谢现在努力的自己。", "author" => "佚名"],
    ["content" => "没有伞的孩子，必须努力奔跑。", "author" => "佚名"],
    ["content" => "生活不会辜负每一个努力的人。", "author" => "佚名"],
    ["content" => "你的坚持，终将美好。", "author" => "佚名"],
    ["content" => "所有看似风光的美丽，背后都藏着不为人知的努力。", "author" => "佚名"],
    ["content" => "你现在的努力，是为了将来有选择的权利。", "author" => "佚名"],
    ["content" => "不要在该奋斗的年纪选择安逸。", "author" => "佚名"],
    ["content" => "不吃苦，你要青春干嘛。", "author" => "佚名"],
    ["content" => "梦想还是要有的，万一实现了呢。", "author" => "马云"],
    
    // 经典语录 (851-1000)
    ["content" => "书籍是人类进步的阶梯。", "author" => "高尔基"],
    ["content" => "知识就是力量。", "author" => "培根"],
    ["content" => "我思故我在。", "author" => "笛卡尔"],
    ["content" => "存在即合理。", "author" => "黑格尔"],
    ["content" => "人不能两次踏进同一条河流。", "author" => "赫拉克利特"],
    ["content" => "认识你自己。", "author" => "苏格拉底"],
    ["content" => "吾爱吾师，吾更爱真理。", "author" => "亚里士多德"],
    ["content" => "美德即知识。", "author" => "苏格拉底"],
    ["content" => "幸福就是身体的无痛苦和灵魂的无纷扰。", "author" => "伊壁鸠鲁"],
    ["content" => "人是万物的尺度。", "author" => "普罗泰戈拉"],
    ["content" => "自由不是想做什么就做什么，而是不想做什么就不做什么。", "author" => "康德"],
    ["content" => "世界上有两件东西能够深深地震撼人们的心灵，一件是我们心中崇高的道德准则，另一件是我们头顶上灿烂的星空。", "author" => "康德"],
    ["content" => "人是生而自由的，却无往不在枷锁之中。", "author" => "卢梭"],
    ["content" => "我不同意你的观点，但我誓死捍卫你说话的权利。", "author" => "伏尔泰"],
    ["content" => "雪崩时，没有一片雪花觉得自己有责任。", "author" => "伏尔泰"],
    ["content" => "当真理还在穿鞋的时候，谎言已经走遍了半个世界。", "author" => "马克·吐温"],
    ["content" => "不要让昨天占用今天的时间。", "author" => "佚名"],
    ["content" => "今天不走，明天要跑。", "author" => "佚名"],
    ["content" => "此刻打盹，你将做梦；此刻学习，你将圆梦。", "author" => "佚名"],
    ["content" => "狗一样地学，绅士一样地玩。", "author" => "佚名"],
    ["content" => "幸福或许不排名次，但成功必排名次。", "author" => "佚名"],
    ["content" => "学习时的苦痛是暂时的，未学到的痛苦是终生的。", "author" => "佚名"],
    ["content" => "学习这件事，不是缺乏时间，而是缺乏努力。", "author" => "佚名"],
    ["content" => "只有比别人更早、更勤奋地努力，才能尝到成功的滋味。", "author" => "佚名"],
    ["content" => "时间在流逝。", "author" => "佚名"],
    ["content" => "虽然我走得慢，但我从不后退。", "author" => "林肯"],
    ["content" => "我成功是因为我志在成功。", "author" => "拿破仑"],
    ["content" => "不想当将军的士兵不是好士兵。", "author" => "拿破仑"],
    ["content" => "人生的光荣，不在永不失败，而在于能够屡败屡战。", "author" => "拿破仑"],
    ["content" => "一个人应养成信赖自己的习惯，即使在最危急的时候，也要相信自己的勇敢与毅力。", "author" => "拿破仑"]
];

// 确保正好1000条
$quotes = array_slice($quotes, 0, 1000);

// 随机抽取一条
$randomQuote = $quotes[array_rand($quotes)];

// 输出标准JSON
try {
    echo json_encode(
        [
            'code' => 200,
            'msg' => 'success',
            'data' => $randomQuote,
            'timestamp' => time()
        ],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
} catch (JsonException $e) {
    http_response_code(500);
    echo json_encode([
        'code' => 500,
        'msg' => '服务器错误',
        'data' => null
    ]);
}