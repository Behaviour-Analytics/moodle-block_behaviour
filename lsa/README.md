# lag-sequential-analysis-tool-php
序列分析工具PHP版

請參考index.php的程式碼來應用吧
https://github.com/pulipulichen/lag-sequential-analysis-tool-php/blob/master/index.php

使用方式為：

````php
<?php
include_once 'sequential_analysis.class.php';
$obs = 'UPSP...'; // 將動詞轉換成一連串的編碼，一個字一個編碼
$sa = new Sequential_analysis($obs);
print_r($sa->export_sign_result("allison_liker")); // 匯出結果，他會以Array的方式顯示
echo json_encode($sa->export_sign_result("allison_liker")); // 匯出結果，以JSON方式顯示
?>
````

如果要用來分析中文，則需要大量記憶體。