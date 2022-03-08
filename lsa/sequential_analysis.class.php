<?php
/**
*/
class Sequential_analysis {

    /**
     * @var string 
     */
    var $obs;

    /**
     * @var array
     */
    var $config_obs_array = array();

    /**
     * @var int 
     */
    var $ns;

    /**
     * @var boolean
     */
    var $repeatable;

    /**
     * @var array 
     */
    var $code_list;

    /**
     * @var array 
     */
    var $code_f;

    /**
     * @var array 
     */
    var $seq_f;

    /**
     * @var int 
     */
    var $lag;

    /**
     * @var string
     */
    var $code_list_string;

    /**
     * @var array 
     */
    var $lag_list;

    /**
     * sequence frequency
     * @var array
     */
    var $sf = array();

    /**
     * @var array
     */
    var $pos_list = array();

    /**
     * @var array
     */
    var $sf_pos_list = array();

    /**
     * code_frequencies
     * @var array 
     */
    var $z_table;

    /**
     * @var array
     */
    var $sign_result;

    /**
     * @var array
     */
    var $position_frequency = array();

    /**
     * @var array
     */
    var $col_total = array();

    /**
     * @param array $obs
     * @param array $codes
     * @param boolean $repeatable
     * @param int $testtype
     */
    function __construct($obs = [], $codes = [], $repeatable = false, $testtype = 1) {

        if (count($obs) === 0) {
            if ($testtype === 1) {
                $obs = $this->sa_create_temp_obs3();
                $codes = ["U","S","T","P","G"];
            } else {
                $obs = $this->sa_create_temp_obs4();
                $codes = ["A","B","C","D"];
            }
        }

        $code_f = [];
        foreach ($codes as $code) {
            $code_f[$code] = 0;
        }

        $this->obs = $obs;
        $this->code_f = $code_f;
        $this->config_codes = $codes;
        $this->code_list = $codes;
        $this->repeatable = $repeatable;
        $this->lag = 2;

        $this->sa_convert_obs();
        $this->cal_frequency();
        $this->calc_code_list_string();
        $this->create_martix_lag_list();
        $this->cal_sf_total();
        $this->create_obs_seq_pos_table();
        $this->cal_sign_result();
    }
    
    /**
     */
    function sa_convert_obs() {

        $obs = $this->obs;
        $output = array();

        foreach ($obs as $k => $code) {
            if (is_array($code)) {
                $output[] = $code;
            } else if ($code === ' ') {
                $output[] = array();
            }
            else {
                $output[] = array($code);
            }
        }
        $this->config_obs_array = $output;
    }

    /**
     */
    function cal_frequency() {
        $config_repeatable = $this->repeatable;

        $n = 0;
        $ns = 0;
        $breaks = 1;
        $seq_f = [];

        $last_event = true;
        $event = false;
        $config_codes = $this->config_codes;
        $code_list = $this->code_list;
        $config_obs_array = $this->config_obs_array;
        $lag = $this->lag;

        for ($i = 0; $i < count($this->config_obs_array); $i++) {
            $events = $this->config_obs_array[$i];

            if (count($events) === 0) {
                $breaks++;
                $last_event = false;
                continue;
            }
            
            if ($config_repeatable === false) {
                if ($last_event !== $event) {
                    $n++;
                }
            }
            else {
                $n++;
            }

            $ns_plus = false;
            for ($j = 0; $j < count($events); $j++) {
                $event = $events[$j];

                if ($config_codes == '' && in_array($event, $code_list) == false) {
                    $code_list[] = $event;
                }

                if (isset($code_f[$event]) === false) {
                    $code_f[$event] = 0;
                }

                if ($config_repeatable === false) {
                    if ($last_event !== $event) {
                        $code_f[$event]++;
                    }
                }
                else {
                    $code_f[$event]++;
                }

                $last_event = $event;

                $next_event = array();
                $break_detect = false;

                if ($i < count($config_obs_array) - ($lag - 1)) {
                    for ($l = 0; $l < $lag - 1; $l++) {
                        $pos = $l + $i + 1;
                        $n_event = $config_obs_array[$pos];

                        if (count($n_event) > 0) {
                            $next_event[] = $n_event;
                        }
                        else {
                            $break_detect = true;
                            break;
                        }
                    }
                    if ($break_detect === true) {
                        continue;
                    }
                }

                $seq_array = array();
                if (count($next_event) > 0) {
                    $seq_array = array($event);
                    $seq_name;
                    for ($ni = 0; $ni < count($next_event); $ni++) {
                        $n_event = $next_event[$ni];

                        $prev_seq = $seq_array;
                        $seq_array = array();
                        for ($e = 0; $e < count($n_event); $e++) {
                            $event = $n_event[$e];
                            for ($p = 0; $p < count($prev_seq); $p++) {
                                $p_seq = $prev_seq[$p];
                                $last_p_seq = mb_substr($p_seq, strlen($p_seq)-1, 1);

                                if ($last_p_seq == $event) {
                                    if ($config_repeatable === false) {
                                        continue;
                                    }
                                }
                                $seq_name = $p_seq . '_' . $event;
                                $seq_array[] = $seq_name;
                            }
                        }
                    }
                }

                if (count($seq_array) > 0 && $break_detect === false) {
                    if ($ns_plus === false) {
                        $ns++;
                        $ns_plus = true;    
                    }

                    $seq_f_last = null;
                    foreach ($seq_array as $s => $seq_name) {
                        if (isset($seq_f[$seq_name]) === false) {
                            $seq_f[$seq_name] = 0;
                        }

                        if ($config_repeatable === false
                                && $seq_name == $seq_f_last) {
                           continue;
                        } else {
                            $seq_f_last = $seq_name;
                        }

                        $seq_f[$seq_name]++;
                        
                        $this->calc_position_frequency($seq_name);
                    }   
                }
            }
        }
        $this->ns = $ns;
        $this->seq_f = $seq_f;
        $this->code_list = $code_list;
        $this->code_f = $code_f;
    }
    
    function calc_position_frequency($seq_name) {
        foreach (explode('_', $seq_name) as $i => $code) {
            if (isset($this->position_frequency[$code]) === false) {
                $this->position_frequency[$code] = array();
            }
            if (isset($this->position_frequency[$code][$i]) === false) {
                $this->position_frequency[$code][$i] = 0;
            }
            $this->position_frequency[$code][$i]++;
        }
    }

    function calc_code_list_string() {

        $code_list_string = '';
        foreach ($this->code_list as $c) {
            if ($code_list_string !== '') {
                $code_list_string .= ', ';
            }
            $code_list_string .= $c;
        }

        $this->code_list_string = $code_list_string;
    }

    function create_martix_lag_list() {
        foreach ($this->code_list as $code) {
            $this->lag_list[] = $code;
        }
    }

    /**
     */
    function cal_sf_total() {

        $seq_f = $this->seq_f;
        foreach ($this->code_list as $i => $row_code) {
            $sf_total = 0;

            $sf_table =  array();

            foreach ($this->lag_list as $col_code) {
                $seq_name = $row_code . '_' . $col_code;

                $sf = 0;

                if (isset($seq_f[$seq_name]) && is_int($seq_f[$seq_name])) {
                    $sf = $seq_f[$seq_name];
                }

                if (isset($this->col_total[$col_code]) === false) {
                    $this->col_total[$col_code] = 0;                    
                }
                $this->col_total[$col_code] = $this->col_total[$col_code] + $sf;

                $sf_table[$col_code] = $sf;
                $sf_total = intval($sf) + intval($sf_total);
            }
            $sf_table["total"] = $sf_total;
            $this->sf[$row_code] = $sf_table;
        }
        $this->sf["col_total"] = $this->col_total;
    }

    /**
     * $this->pos_list
     */
    function create_obs_seq_pos_table() {

        foreach ($this->sf as $row_code => $row) {
            if ($row_code === "col_total") {
                continue;
            }
        
            $sf_total = $row['total'];
            if (!is_int($sf_total)) {
                $sf_total = 0;
            }
        
            if ($sf_total > 0) {
                $pos_list = array();
                foreach ($row as $j => $f) {
                    if ($j === "total") {
                        continue;
                    }
                    $pos = ($f / $sf_total);
                    $pos_list[$j] = $pos;
                }
                $this->pos_list[$row_code] = $pos_list;
            }
        }
    }

    function sa_create_temp_obs() {
        $temp = 'ABBDC(CA)ABCB(DB)CBBB(BC)DDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBC';
        return $temp;
    }
    
    function sa_create_temp_obs2() {
        $temp = 'USPTPTPGTPTGTPGTGPTPGTGPSTPTGTSPGPSUSTPTGTUTSPGPSGTPTGPGSUSTUTSPSGTPTGPGSUSTUTSPSGTPTGPGUSUTUPUGSTSPSGTPTGPGUSUTUPUGSTSPSGTPTGPG';
        return $temp;
    }

    function sa_create_temp_obs3() {
        $s = $this->sa_create_temp_obs2();
        return str_split($s);
    }
    function sa_create_temp_obs4() {
        $s = $this->sa_create_temp_obs();
        $split = str_split($s);
        $obs = [];
        for ($i = 0; $i < count($split); $i++) {
            if ($split[$i] == '(') {
                $arr = [];
                $i++;
                while ($i < count($split) && $split[$i] != ')') {
                    $arr[] = $split[$i];
                    $i++;
                }
                $obs[] = $arr;
            } else {
                $obs[] = $split[$i];
            }
        }
        return $obs;
    }

    function cal_sign_result() {

        $this->cal_z_score_zero_order();
        $this->cal_z_score_code_frequencies();
        $this->cal_z_score_joint_frequency();
        $this->cal_z_score_allison_liker();
        
    }
    
    /**
     * Zero-order Model
     */
    function cal_z_score_zero_order() {
        $code_list = $this->code_list;
        $code_f = $this->code_f;
        $lag_list = $this->lag_list;
        $seq_f = $this->seq_f;
        $ns = $this->ns;
        
        $sign_result = array();

        $z_table = array();

        foreach ($code_list as $row_code) {

            $cf = 0;
            if (isset($code_f[$row_code]) && is_int($code_f[$row_code])) {
                $cf = $code_f[$row_code];
            }

            $z_row = array();
            foreach ($lag_list as $col_code) {

                $seq_name = $row_code . '_' . $col_code;

                $sf = 0;

                if (isset($seq_f[$seq_name]) && is_int($seq_f[$seq_name])) {
                    $sf = $seq_f[$seq_name];
                }

                $exp_pos = 1 / ((count($this->code_list) * (count($this->code_list) - 1)));
                
                $z = ($sf - ($ns * $exp_pos)) / sqrt($ns * $exp_pos * ( 1 - $exp_pos) );
                $z_row[$col_code] = $z;

                if ($z > 1.96) {
                    $sign_result[$seq_name] = $z;
                }   
            }
            $z_table[$row_code] = $z_row;
        }
        
        $this->z_table["zero_order"] = $z_table;
        $this->sign_result["zero_order"] = $sign_result;
    }
    
    /**
     * First-order Model
     */
    function cal_z_score_code_frequencies() {
        $code_list = $this->code_list;
        $code_f = $this->code_f;
        $lag_list = $this->lag_list;
        $seq_f = $this->seq_f;
        $ns = $this->ns;
        
        $sign_result = array();

        $z_table = array();

        foreach ($code_list as $row_code) {

            $cf = 0;
            if (isset($code_f[$row_code]) && is_int($code_f[$row_code])) {
                $cf = $code_f[$row_code];
            }

            $z_row = array();
            foreach ($lag_list as $col_code) {
                $seq_name = $row_code . '_' . $col_code;

                $sf = 0;

                if (isset($seq_f[$seq_name]) && is_int($seq_f[$seq_name])) {
                    $sf = $seq_f[$seq_name];
                }
                $pp = ($this->code_f[$row_code] / $this->ns);
                $exp_pos = $this->_calc_prop_targets($col_code, $row_code, true, $pp);

                if ($ns * $exp_pos * ( 1 - $exp_pos) > 0) {
                    $z = ($sf - ($ns * $exp_pos)) / sqrt($ns * $exp_pos * ( 1 - $exp_pos) );
                }
                else {
                    $z = 0;
                }

                $z_row[$col_code] = $z;

                if ($z > 1.96) {
                    $sign_result[$seq_name] = $z;
                }   
            }
            $z_table[$row_code] = $z_row;
        }
        
        $this->z_table["code_frequencies"] = $z_table;
        $this->sign_result["code_frequencies"] = $sign_result;
    }
    
    function cal_z_score_joint_frequency() {
        $code_list = $this->code_list;
        $code_f = $this->code_f;
        $lag_list = $this->lag_list;
        $seq_f = $this->seq_f;
        $ns = $this->ns;
        
        $sign_result = array();

        $z_table = array();

        foreach ($code_list as $row_code) {

            $cf = 0;
            if (isset($code_f[$row_code]) && is_int($code_f[$row_code])) {
                $cf = $code_f[$row_code];
            }

            $z_row = array();
            foreach ($lag_list as $col_code) {
                $seq_name = $row_code . '_' . $col_code;

                $sf = 0;

                if (isset($seq_f[$seq_name]) && is_int($seq_f[$seq_name])) {
                    $sf = $seq_f[$seq_name];
                }

                $pg = $this->_calc_prop_targets($col_code, $row_code);
                $fp = $this->code_f[$row_code];
                
                if (($fp * $pg * ( 1 - $pg)) > 0) {
                    $z = ($sf - ($fp * $pg)) / sqrt($fp * $pg * ( 1 - $pg) );
                }
                else {
                    $z = 0;
                }
                $z_row[$col_code] = $z;

                if ($z > 1.96) {
                    $sign_result[$seq_name] = $z;
                }   
            }
            $z_table[$row_code] = $z_row;
        }
        
        $this->z_table["joint_frequency"] = $z_table;
        $this->sign_result["joint_frequency"] = $sign_result;
    }
    
    function _calc_prop_targets($col_code, $row_code, $pos_first = false, $prop = 1) {
        $col_codes = explode('_', $col_code);
        foreach ($col_codes as $i => $code) {
            $prev_code = $row_code;
            if ($i > 0) {
                $prev_code = $col_codes[$i-1];
            }

            $fg = 0;
            $p = 0;
            
            if ($pos_first === false) {
                $p = $i+1;
                if (isset($this->position_frequency[$code][($p)])) {
                    $fg = $this->position_frequency[$code][($p)];
                }
            } else {
                $fg = $this->code_f[$code];
            }
            
            if ($this->repeatable === true) {
                $prop = $prop * ($fg / $this->ns);
            } else if (($this->ns - $this->code_f[$prev_code]) > 0) {
                $prop = $prop * ($fg / ($this->ns - $this->code_f[$prev_code]));
            }
        }
        return $prop;
    }

    function cal_z_score_allison_liker() {
        $code_list = $this->code_list;
        $code_f = $this->code_f;
        $lag_list = $this->lag_list;
        $seq_f = $this->seq_f;
        $ns = $this->ns;
        
        $sign_result = array();

        $z_table = array();

        foreach ($code_list as $row_code) {

            $cf = 0;
            if (isset($code_f[$row_code]) && is_int($code_f[$row_code])) {
                $cf = $code_f[$row_code];
            }

            $z_row = array();
            foreach ($lag_list as $col_code) {
                $seq_name = $row_code . '_' . $col_code;

                $sf = 0;

                if (isset($seq_f[$seq_name]) && is_int($seq_f[$seq_name])) {
                    $sf = $seq_f[$seq_name];
                }
                
                $fpg = $sf;
                $fp = $this->sf[$row_code]['total'];
                $pg = $this->_calc_prop_targets($col_code, $row_code);
                
                $pp = ($fp / $this->ns);

                if (( $fp * $pg * (1-$pg) * (1-$pp) ) > 0) {
                    $z = ($fpg - ($fp * $pg)) / sqrt( $fp * $pg * (1-$pg) * (1-$pp) );
                } else {
                    $z = 0;
                }
                $z_row[$col_code] = $z;

                if ($z > 1.96) {
                    $sign_result[$seq_name] = $z;
                }   
            }
            $z_table[$row_code] = $z_row;
        }
        
        $this->z_table["allison_liker"] = $z_table;
        $this->sign_result["allison_liker"] = $sign_result;
    }
    

    /**
     * @param {String|NULL} $target_model
     * @return array
     */
    function export_sign_result($target_model = NULL) {
        $export = array();
        foreach ($this->sign_result as $model => $results) {
            if (count($results) > 0) {
                $ary = array();
                foreach ($results as $seq => $z) {
                    $seqs = explode('_', $seq);
                    $source = $seqs[0];
                    $target = $seqs[1];
                    $frequency = $this->sf[$source][$target];
                    $ary[] = array(
                        "source" => $source,
                        "target" => $target,
                        "value" => $z,
                        "label" => round($z, 2),
                        "frequency" => $frequency
                    );
                }
                $export[$model] = $ary;
            }
        }
        
        if (is_null($target_model) === false) {
            if (isset($export[$target_model])) {
                $export = $export[$target_model];
            }
            else {
                $export = array();
            }
        }
        
        return $export;
    }
    
    function export_sign_result_csv($target_model = NULL) {
      $export = $this->export_sign_result($target_model);
      $exportCSV = array();
      $exportCSV[] = 'source,target,label,value,frequency';
      foreach ($export as $item) {
        $exportCSV[] = $item['source'].','.$item['target'].','.$item['label'].','.$item['value'].','.$item['frequency'];
      }
      return implode("\n", $exportCSV);
    }

    /**
     * @param {string} $table
     * @return string
     */
    static function table_draw($table, $show_sig = false) {
        $html = '<table border="1">';
        
        $thead_th = array();
        
        $first_tr = true;
        $tbody = "<tbody>";
        foreach ($table as $row_code => $row) {
            
            $tr = "<tr>";
            $tr .= "<th>" . $row_code . "</th>";
            
            if (is_array($row)) {
                foreach ($row as $col_code => $cell) {
                    if ($first_tr === true) {
                        $thead_th[] = $col_code;
                    }

                    if ($show_sig === true && $cell > 1.96) {
                        $tr .= "<td style='color:red;'>" . $cell . "</td>";
                    }
                    else {
                        $tr .= "<td>" . $cell . "</td>";
                    }
                }
            }
            else {
                $cell = $row;
                if ($show_sig === true && $cell > 1.96) {
                    $tr .= "<td style='color:red;'>" . $cell . "</td>";
                }
                else {
                    $tr .= "<td>" . $cell . "</td>";
                }
            }
            $tr .= "</tr>";
            $tbody .= $tr;
            $first_tr = false;
        }
        $tbody .= "</tbody>";
        
        $thead = "<thead><th>&nbsp;</th>";
        foreach ($thead_th as $th) {
            $thead .= "<th>" . $th . "</th>";
        }
        $thead .= "</thead>";
        
        $html .= $thead . $tbody . "</table>";
        return $html;
    }
}
