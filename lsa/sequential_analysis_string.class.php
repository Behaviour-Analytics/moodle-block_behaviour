<?php
/**
 * Usage:
 * include_once 'sequential_analysis.class.php';
 * $obs = "ABABCBCA";
 * $codes = "ABS";
 * $repeatable = FALSE;
 * $lag = 2;
 * $sa = new Sequential_analysis($obs, $codes, $repeatable, $lag);
 * print_r($sa->export_sign_result("allison_liker"));
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
    var $n;

    /**
     * @var int 
     */
    var $ns;

    /**
     * @var int 
     */
    var $breaks;

    /**
     * @var boolean
     */

    /**
     * true, false, auto
     * @var string 
     */
    var $repeatable = TRUE;

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
     * @var array
     */
    //var $exp_pos_table;

    /**
     * @var array
     */
    //var $exp_f_table;

    /**
     * @var array
     */
    //var $last_ns_table;

    /**
     * @var string
     */
    //var $last_ns_message;

    /**
     * @var array
     */
    var $exp_pos_list;

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
     * @param {string} $obs
     * @param {string} $codes
     * @param {boolean} $repeatable
     */
    function __construct($obs = '', $codes = '', $repeatable = FALSE, $testtype = 1) {

        $lag = 2;
        $obs = trim($obs);
        $obs = str_replace("\r", " ", $obs);
        $obs = str_replace("\n", " ", $obs);

        if (is_string($codes)) {
            $codes = trim($codes);
        }
        else if (is_array($codes)) {
            $codes_string = "";
            foreach ($codes AS $c) {
                $codes_string .= $c;
            }
            $codes = $codes_string;
        }

        if ($obs === "") {
            if ($testtype === 1) {
                $obs = $this->sa_create_temp_obs2();
                $codes = "USTPG";
            } else {
                $obs = $this->sa_create_temp_obs();
                $codes = "ABCD";
            }
        }

        $code_list = [];
        $code_f = [];

        $config_obs = $obs;
        /*if ($config_obs === '') {
            return;
            }*/

        $this->obs = $obs;

        $config_codes = $codes;

        if ($config_codes != '') {
            //var _last_code = null;
            for ($i = 0; $i < mb_strlen($config_codes); $i++) {
                $code = mb_substr($config_codes, $i, 1);
                $code_list[] = $code;
                $code_f[$code] = 0;
            }
        }

        $this->code_f = $code_f;
        $this->config_codes = $config_codes;
        $this->code_list = $code_list;
        $this->repeatable = $repeatable;
        $this->lag = $lag;

        $this->sa_convert_obs();
        $this->cal_frequency();
        $this->calc_code_list_string();
        $this->create_martix_lag_list();
        $this->cal_sf_total();
        $this->create_obs_seq_pos_table();
        $this->cal_sign_result();
    }   //end of function __construct(
    
    /**
     */
    function sa_convert_obs() {

        $obs = $this->obs;
        //print_r($obs);

        $output = array();

        $break_list = array(' ', '\t', '\n');

        for ($i = 0; $i < mb_strlen($obs); $i++) {
            $code = mb_substr($obs, $i, 1);

            if (in_array($code, $break_list) === true) {
                $output[] = array();
            }
            else if ($code == '(') {
                $multi_code = array();
                $i++;
                while (mb_substr($obs, $i, 1) !== ')') {
                    $code = mb_substr($obs, $i, 1);
                    $multi_code[] = $code;
                    $i++; 
                }
                $output[] = $multi_code;
            }
            else {
                $output[] = array($code);
            }
        }
        //print_r($output);
        $this->config_obs_array = $output;
    }   // end of function sa_convert_obs(

    /**
     */
    function cal_frequency() {
        $config_repeatable = $this->repeatable;

        $n = 0;
        $ns = 0;
        $breaks = 1;
        $seq_f = [];

        $last_event = TRUE;
        $event = FALSE;
        $config_codes = $this->config_codes;
        $code_list = $this->code_list;
        $config_obs_array = $this->config_obs_array;
        $lag = $this->lag;
        //$proc_first_event = true;
        //print_r($this->config_obs_array);

        for ($i = 0; $i < count($this->config_obs_array); $i++) {
            $events = $this->config_obs_array[$i];

            if (count($events) === 0) {
                $breaks++;
                $last_event = FALSE;
                continue;
            }
            
            if ($config_repeatable === FALSE) {
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

                if ($config_codes == '' && in_array($event, $code_list) == FALSE) {
                    $code_list[] = $event;
                }

                if (isset($code_f[$event]) === FALSE) {
                    $code_f[$event] = 0;
                }

                if ($config_repeatable === FALSE) {
                    if ($last_event !== $event) {
                        $code_f[$event]++;
                    }
                }
                else {
                    $code_f[$event]++;
                }

                $last_event = $event;

                $next_event = array();
                $break_detect = FALSE;

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
                    if ($break_detect === TRUE) {
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
                                //$last_p_seq = mb_substr($p_seq, count($p_seq)-1, 1);
                                $last_p_seq = mb_substr($p_seq, strlen($p_seq)-1, 1);

                                if ($last_p_seq == $event) {
                                    //if ($config_repeatable == 'auto') {
                                    //    $this->repeatable = true;
                                    //}
                                    if ($config_repeatable === FALSE) {
                                        continue;
                                    }
                                }

                                $seq_name = $p_seq . $event;
                                $seq_array[] = $seq_name;
                            }
                        }
                    }
                }

                if (count($seq_array) > 0 && $break_detect === FALSE) {
                    if ($ns_plus === false) {
                        $ns++;
                        $ns_plus = true;    
                    }

                    $seq_f_last = null;
                    foreach ($seq_array AS $s => $seq_name) {
                        //var _seq_name = _seq_array[_s];
                        if (isset($seq_f[$seq_name]) === false) {
                            $seq_f[$seq_name] = 0;
                        }

                        if ($config_repeatable === FALSE
                                && $seq_name == $seq_f_last) {
                           continue;
                        }
                        else {
                            $seq_f_last = $seq_name;
                        }

                        $seq_f[$seq_name]++;
                        
                        $this->calc_position_frequency($seq_name);
                    }   
                }
            }
        }
        $this->n = $n;
        $this->ns = $ns;
        $this->breaks = $breaks;
        $this->seq_f = $seq_f;
        $this->code_list = $code_list;
        $this->code_f = $code_f;
    }
    
    function calc_position_frequency($seq_name) {
        for ($i = 0; $i < mb_strlen($seq_name); $i++) {
            $code = mb_substr($seq_name, $i, 1);
            if (isset($this->position_frequency[$code]) === FALSE) {
                $this->position_frequency[$code] = array();
            }
            if (isset($this->position_frequency[$code][$i]) === FALSE) {
                $this->position_frequency[$code][$i] = 0;
            }
            $this->position_frequency[$code][$i]++;
            //echo $code.$i . "-";
        }
    }

    function calc_code_list_string() {

        $code_list_string = '';
        foreach ($this->code_list AS $c) {
            if ($code_list_string !== '') {
                $code_list_string .= ', ';
            }
            //$code_list_string += $c;
            $code_list_string .= $c;
        }

        $this->code_list_string = $code_list_string;
    }

    /**
     */
    function create_lag_list($lag, $lag_list = NULL) {
        if ($lag_list === NULL) {
            $lag_list = $this->code_list;
        }

        $new_lag_list = array();

        foreach ($lag_list AS $lag_name) {
            //var _lag_name = _lag_list[_i];

            foreach ($lag_list AS $code) {
                $name = $lag_name . $code;
                $new_lag_list[] = $name;
            }
        }

        $new_lag = $lag - 1;
        if ($new_lag > 1) {
            $lag_list = $this->create_lag_list($new_lag, $new_lag_list);
        }
        else {
            return $lag_list;
        }
        
        $this->lag_list = $lag_list;
    }
    
    function create_martix_lag_list($name = "") {
        if (mb_strlen($name) === ($this->lag - 1)) {
            $this->lag_list[] = $name;
        }
        else {
            foreach ($this->code_list AS $code) {
                $new_name = $name . $code;
                //echo $name . "-";
                $this->create_martix_lag_list($new_name);
            }
        }
    }

    /**
     */
    function cal_sf_total() {

        $seq_f = $this->seq_f;
        //print_r($this->lag_list);
        foreach ($this->code_list AS $i => $row_code) {
            $sf_total = 0;

            $sf_table =  array();

            foreach ($this->lag_list AS $col_code) {
                $seq_name = $row_code . $col_code;

                $sf = 0;

                if (isset($seq_f[$seq_name]) && is_int($seq_f[$seq_name])) {
                    $sf = $seq_f[$seq_name];
                }

                if (isset($this->col_total[$col_code]) === false) {
                    $this->col_total[$col_code] = 0;                    
                }
                $this->col_total[$col_code] = $this->col_total[$col_code] + $sf;
                //echo $row_code;

                //if ($sf === 0) {
                //   $sf = "0";
                //}

                $sf_table[$col_code] = $sf;
                $sf_total = intval($sf) + intval($sf_total);
            }

            //if ($sf_total === 0) {
            //    $sf_total = '0';
            //}

            //$this->sf_total[$row_code] = $sf_total;
            $sf_table["total"] = $sf_total;
            $this->sf[$row_code] = $sf_table;
        }
        $this->sf["col_total"] = $this->col_total;
    }

    /**
     * $this->pos_list
     */
    function create_obs_seq_pos_table() {

        //var _table = _f_table.clone();

        //_table.find('caption').html('編碼轉換機率表');
    
        //var _tr_list = _table.find('tbody tr');
        //for (var _i = 0; _i < _tr_list.length; _i++)
        foreach ($this->sf AS $row_code => $row) {
            if ($row_code === "col_total") {
                continue;
            }
        
            //var _tr = _tr_list.eq(_i);
            //var _sf_total = parseInt(_tr.find('.sf-total').html());
            $sf_total = $row['total'];
            //if (isNaN(_sf_total)) {
            if (is_int($sf_total) === FALSE) {
                $sf_total = 0;
            }
        
            if ($sf_total === 0) {
                //_tr.find('td').html('0.00');
            } else {
                //var _td_list = _tr.find('td');
                //$list = array();

                //for (var _j = 0; _j < _td_list.length; _j++)
                $pos_list = array();
                foreach ($row AS $j => $f) {
                    if ($j === "total") {
                        continue;
                    }

                    //var _f = parseInt(_td_list.eq(_j).html());

                    //if (isNaN(_f))
                    //    _f = 0;
                
                    //var _pos = (_f / _sf_total).toFixed(2);
                    $pos = ($f / $sf_total);
                
                    //_td_list.eq(_j).html(_pos);
                    $pos_list[$j] = $pos;
                }
                $this->pos_list[$row_code] = $pos_list;
            }
        }
    
        //return _table;
    }

    /**
     * @deprecated
     *//*
    function create_obs_f_table() {
    
        $sf_pos_list = array();

        $code_f = $this->code_f;
        $n = $this->n;
    
        foreach ($this->code_list AS $row_code) {
        
            $cf = 0;
            if (isset($code_f[$row_code]) && is_int($code_f[$row_code])) {
                $cf = $code_f[$row_code];
            }

            $sf_pos_list[$row_code]["sf"] = $cf;
            $sf_pos_list[$row_code]["pos"] = ($cf / $n);
        }
        $this->sf_pos_list = $sf_pos_list;
        }*/

    /**
     * First-Order Model Expect Position Table
     */
    function create_exp_pos_1_table() {

        $exp_table = array();
        $code_f = $this->code_f;
        $n = $this->n;

        foreach ($this->code_list AS $row_code) {
        
            $cf = 0;
            if (isset($code_f[$row_code]) && is_int($code_f[$row_code])) {
                $cf = $code_f[$row_code];
            }
        
            $exp_row = array();
        
            foreach ($this->lag_list AS $j => $col_code) {

                //var _col_code = _lag_list[_j];

                $row_code_f = $cf;
                $exp_pos = $cf / $n;
            
                for ($k = 0; $k < mb_strlen($col_code); $k++) {

                    $col_c = mb_substr($col_code, $k, 1);
                
                    $f = 0;
                    if (isset($code_f[$col_c]) && is_int($code_f[$col_c])) {
                        $f = $code_f[$col_c];
                    }
                    
                    $p = 0;
                    if ($this->repeatable === TRUE) {
                        $p = $f / $n;
                    }
                    else {
                        $p = $f / ($n - $row_code_f);
                    }
                
                    //if ($row_code === "P" && $col_code === "G") {
                    //    print_r(array($row_code_f, $n, $f));
                    //}
                
                    $exp_pos = $exp_pos * $p;
                }
            
                $exp_row[$col_code] = $exp_pos;
            }

            $exp_table[$row_code] = $exp_row;
        }
    
        $this->exp_pos_table = $exp_table;
    
    }   //  function create_exp_pos_1_table($n, $code_list, $code_f, $repeatable, $lag, $lag_list) {

    /**
     * (zero-order model)
     * @deprecated since version 20160725
     *//*
    function create_exp_pos_0_table() {
    
        //$n = $this->n; 
        $code_list = $this->code_list; 
        $code_f = $this->code_f; 
        //$repeatable = $this->repeatable; 
        $lag = $this->lag; 
        $lag_list = $this->lag_list;
    
        //print_r($code_list);
        //    $exp_pos = (1 / count($code_list));
        //
        //    for ($i = 0; $i < $lag - 1; $i++) {
        //        $exp_pos = $exp_pos * $exp_pos;
        //    }
        $exp_pos = 1 / (count($code_list) * (count($code_list) - 1) );
    
        $exp_table = array();
        foreach ($code_list AS $i => $row_code) {
            $cf = 0;

            if (is_int($code_f[$row_code])) {
                $cf = $code_f[$row_code];
            }
        
            $exp_row = array();
            //for (var _j in _lag_list) {
            foreach ($lag_list AS $j => $col_code ) {
                $exp_row[$col_code] = $exp_pos;
            }
            $exp_table[$row_code] = $exp_row;
        }
        $this->exp_pos_table = $exp_table;
    
        //return _table;
        }*/

    /**
     * @deprecated since version 20160725
     *//*
    function create_exp_f_table() {
    
        //var _exp_f_table = _exp_pos_table.clone();
    
        //var _td_list = _exp_f_table.find('td');
        $exp_f_table = $this->exp_pos_table;
        $ns = $this->ns;
    
        //for ($i = 0; $i < count($td_list); $i++) {
        foreach ($exp_f_table AS $row_code => $row) {
            foreach ($row AS $col_code => $exp_pos) {
                if (is_float($exp_pos) === FALSE) {
                    continue;
                }
                else {
                    $exp_f = $exp_pos * $ns;
                    $exp_f_table[$row_code][$col_code] = $exp_f;
                }
            }
        }
        //return _exp_f_table;
        $this->exp_f_table = $exp_f_table;
        }*/

    /**
     * @deprecated since version 20160725
     *//*
    function create_last_ns_table() {

        $last_ns_table = $this->exp_pos_table;
        $ns = $this->ns;
    
        $max_ns = 0;
        //for (var _i = 0; _i < _td_list.length; _i++)
        foreach ($last_ns_table AS $row_code => $row) {

            foreach ($row AS $col_code => $exp_pos) {
                //var _exp_pos = _td_list.eq(_i).attr('exp_pos');
                //_exp_pos = parseFloat(_exp_pos);
                
                //if (isNaN(_exp_pos))
                //    continue;
                if (is_float($exp_pos) === FALSE) {
                    continue;
                }
                else if ($exp_pos > 0) {   

                    $last_ns = 9 / ($exp_pos * (1 - $exp_pos));
                
                    $last_ns_dis = $last_ns;
                
                    $last_ns_table[$row_code][$col_code] = $last_ns_dis;
                
                    $max_ns = $last_ns;
                }
                else {
                    $last_ns_table[$row_code][$col_code] = NULL;
                }
            }
        }
        if ($ns < $max_ns) {
            $this->last_ns_message = '' . $ns . '&#65292;' . $max_ns;
        }
        else {
            $this->last_ns_message = '' . $ns . '&#65292;' . $max_ns;
        }

        //return _table;
        $this->last_ns_table = $last_ns_table;
        }*/

    function sa_create_temp_obs() {
        $temp = 'ABBDC(CA)ABCB(DB)CBBB(BC)DDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBCABBDCCAABCBDBCBBBBCDDBCBCBCBBDBCDB BCBBDBCBAABCDDBCBAABAAABBBAA BDBABABBBCBBDBDBBCDBC';
        return $temp;
    }
    
    function sa_create_temp_obs2() {
        $temp = 'USPTPTPGTPTGTPGTGPTPGTGPSTPTGTSPGPSUSTPTGTUTSPGPSGTPTGPGSUSTUTSPSGTPTGPGSUSTUTSPSGTPTGPGUSUTUPUGSTSPSGTPTGPGUSUTUPUGSTSPSGTPTGPG';
        return $temp;
    }

    function sa_create_temp_obs3() {
        $s = sa_create_temp_obs2();
        return str_split($s);
    }
    
    /**
     * @deprecated 20160725 z-score
     */
    /*
    function cal_exp_pos_list() {
        
        $first_order = $this->first_order;
        $code_list = $this->code_list; 
        $lag = $this->lag;

        //var _exp_pos_list = [];
        $exp_pos_list = array();

        if ($first_order === TRUE) {

            //var _exp_pos_td_list = _exp_pos_table.find('td:not(.sf-total)');
            $exp_pos_table = $this->exp_pos_table;

            foreach ($exp_pos_table AS $row_code => $row) {
                foreach ($row AS $col_code => $pos) {
                    if (is_float($pos) === false) {
                        $pos = 0;
                    }
                    $exp_pos_list[$row_code][$col_code] = $pos;
                }
            }
        } 
        else {
            $exp_pos = (1 / count($code_list));
            for ($i = 0; $i < $lag - 1; $i++) {
                $exp_pos = $exp_pos * $exp_pos;
            }

            foreach ($this->exp_pos_table AS $row_code => $row) {
                foreach ($row AS $col_code => $pos) {
                    $exp_pos_list[$row_code][$col_code] = $exp_pos;
                }
            }
        }

        // 注意，列表被我改成table了
        $this->exp_pos_list = $exp_pos_list;
    }   // function cal_exp_pos_list() {
    */

    function cal_sign_result() {

        $this->cal_z_score_zero_order();
        $this->cal_z_score_code_frequencies();
        $this->cal_z_score_joint_frequency();
        //$this->cal_z_score_transitional_probability();
        $this->cal_z_score_allison_liker();
        //$this->cal_z_score_allison_liker2();
        
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

        foreach ($code_list AS $row_code) {

            $cf = 0;
            if (isset($code_f[$row_code]) && is_int($code_f[$row_code])) {
                $cf = $code_f[$row_code];
            }

            $z_row = array();
            foreach ($lag_list AS $col_code) {

                //var _col_code = _code_list[_j];

                $seq_name = $row_code . $col_code;

                $sf = 0;

                if (isset($seq_f[$seq_name]) && is_int($seq_f[$seq_name])) {
                    $sf = $seq_f[$seq_name];
                }

                $exp_pos = 1 / ((count($this->code_list) * (count($this->code_list) - 1)));
                //echo $exp_pos;
                
                $z = ($sf - ($ns * $exp_pos)) / sqrt($ns * $exp_pos * ( 1 - $exp_pos) );

//                if (is_float($z) === FALSE) {
//                    $z = 0;
//                }

                $z_row[$col_code] = $z;

                if ($z > 1.96) {
                    //_td.addClass('sign');

                    $sign_result[$seq_name] = $z;
                }   

                //_e++;
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

        foreach ($code_list AS $row_code) {

            $cf = 0;
            if (isset($code_f[$row_code]) && is_int($code_f[$row_code])) {
                $cf = $code_f[$row_code];
            }

            $z_row = array();
            foreach ($lag_list AS $col_code) {
                //echo $row_code . "-" . $col_code . "|";

                //var _col_code = _code_list[_j];

                $seq_name = $row_code . $col_code;

                $sf = 0;

                if (isset($seq_f[$seq_name]) && is_int($seq_f[$seq_name])) {
                    $sf = $seq_f[$seq_name];
                }

                //$exp_pos = $this->exp_pos_list[$row_code][$col_code];
                // p -> g
                //$pp = 0;
                //if (isset($this->sf[$row_code]) && isset($this->sf[$row_code]["total"])) {
                //    $pp = $this->sf[$row_code]["total"] / $this->ns;
                //}
                $pp = ($this->code_f[$row_code] / $this->ns);
                //echo $pp . "+";
                
                $exp_pos = $this->_calc_prop_targets($col_code, $row_code, TRUE, $pp);
         
//                if (isset($this->sf[$col_code]) && $this->sf[$col_code]["total"]) {
//                    $fg = $this->sf[$col_code]["total"];
//                }
//                if ($row_code === "P" && $col_code === "G") {
//                    echo $exp_pos . "!!!";
//                }
//                //$fg = $this->sf["col_total"][$col_code];
//                
//                
//                if ($this->repeatable === true) {
//                    $exp_pos = $pp * ($fg / $this->ns);
//                }
//                else {
//                    $exp_pos = $pp * ($fg / ($this->ns - $this->sf[$row_code]["total"]));
//                }
                
                
                //echo $pp."-".$fg . "|";
                
                
                
                if ($ns * $exp_pos * ( 1 - $exp_pos) > 0) {
                    $z = ($sf - ($ns * $exp_pos)) / sqrt($ns * $exp_pos * ( 1 - $exp_pos) );
                    //echo $z;
                    // 正確解答 P->G: 0.71371376594677
                }
                else {
                    $z = 0;
                }

//                if (is_float($z) === FALSE) {
//                    $z = 0;
//                }

                $z_row[$col_code] = $z;

                if ($z > 1.96) {
                    //_td.addClass('sign');

                    $sign_result[$seq_name] = $z;
                }   

                //_e++;
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

        foreach ($code_list AS $row_code) {

            $cf = 0;
            if (isset($code_f[$row_code]) && is_int($code_f[$row_code])) {
                $cf = $code_f[$row_code];
            }

            $z_row = array();
            foreach ($lag_list AS $col_code) {

                //var _col_code = _code_list[_j];

                $seq_name = $row_code . $col_code;

                $sf = 0;

                if (isset($seq_f[$seq_name]) && is_int($seq_f[$seq_name])) {
                    $sf = $seq_f[$seq_name];
                }

                //$exp_pos = $this->exp_pos_list[$row_code][$col_code];
//                $fg = $this->sf["col_total"][$col_code];
//                
//                if ($this->repeatable === true) {
//                    $pg = ($fg / $this->ns);
//                }
//                else {
//                    $pg = ($fg / ($this->ns - $this->sf[$row_code]['total']) );
//                }
                $pg = $this->_calc_prop_targets($col_code, $row_code);
                
                //$fp = $this->sf[$row_code]['total'];
                $fp = $this->code_f[$row_code];
                //$exp_pos = ($fp / $this->ns) * $pg;
                
                if (($fp * $pg * ( 1 - $pg)) > 0) {
                    $z = ($sf - ($fp * $pg)) / sqrt($fp * $pg * ( 1 - $pg) );
                    
                    // P->G: 0.67193684090529
                }
                else {
                    $z = 0;
                }
                
//                if ($row_code === "P" && $col_code === "G") {
//                    echo "----" . (10 - (30*0.278) ) / sqrt( 30 * 0.278 * (1-0.278) ) . "---";
//                    echo "---" . (10 - (30*0.27835051546392 ) ) / sqrt( 30 * 0.27835051546392  * (1-0.27835051546392 )) . "---";
//                }

//                if (is_float($z) === FALSE) {
//                    $z = 0;
//                }

                $z_row[$col_code] = $z;

                if ($z > 1.96) {
                    $sign_result[$seq_name] = $z;
                }   

                //_e++;
            }
            $z_table[$row_code] = $z_row;
        }
        
        $this->z_table["joint_frequency"] = $z_table;

        $this->sign_result["joint_frequency"] = $sign_result;
    }
    
    function _calc_prop_targets($col_code, $row_code, $pos_first = FALSE, $prop = 1) {
        //echo $col_code."+";
        for ($i = 0; $i < mb_strlen($col_code); $i++) {
            $code = mb_substr($col_code, $i, 1);
            $prev_code = $row_code;
            if ($i > 0) {
                $prev_code = mb_substr($col_code, ($i-1), 1);
            }

            $fg = 0;
            $p = 0;
            
            if ($pos_first === FALSE) {
                $p = $i+1;
                if (isset($this->position_frequency[$code][($p)])) {
                    $fg = $this->position_frequency[$code][($p)];
                }
            }
            else {
                $fg = $this->code_f[$code];
            }
            
//            if ($pos_first === FALSE) {
//                $p = $i+1;
//            }
//            if (isset($this->position_frequency[$code][($p)])) {
//                $fg = $this->position_frequency[$code][($p)];
//            }
                
//            if ($row_code === "P" && $col_code === "G" && $pos_first === TRUE) {
//                echo $fg . "+" . $p . "---";
//            }
            
            if ($this->repeatable === true) {
                $prop = $prop * ($fg / $this->ns);
            }
            else if (($this->ns - $this->code_f[$prev_code]) > 0) {
                //$prop = $prop * ($fg / ($this->ns - $this->sf[$prev_code]["total"]));
                $prop = $prop * ($fg / ($this->ns - $this->code_f[$prev_code]));
            }
        }
        return $prop;
    } 
    
    /**
     * @deprecated since version 20160725 joint_frequency
     *//*
    function cal_z_score_transitional_probability() {
        $code_list = $this->code_list;
        $code_f = $this->code_f;
        $lag_list = $this->lag_list;
        $seq_f = $this->seq_f;
        $ns = $this->ns;
        
        $sign_result = array();

        $z_table = array();

        foreach ($code_list AS $row_code) {

            $cf = 0;
            if (isset($code_f[$row_code]) && is_int($code_f[$row_code])) {
                $cf = $code_f[$row_code];
            }

            $z_row = array();
            foreach ($lag_list AS $col_code) {

                //var _col_code = _code_list[_j];

                $seq_name = $row_code . $col_code;

                $sf = 0;

                if (isset($seq_f[$seq_name]) && is_int($seq_f[$seq_name])) {
                    $sf = $seq_f[$seq_name];
                }

                //$exp_pos = $this->exp_pos_list[$row_code][$col_code];
                if ($this->repeatable === true) {
                    $pg = ($this->sf["col_total"][$col_code] / $this->ns);
                }
                else {
                    $pg = ($this->sf["col_total"][$col_code] / ($this->ns - $this->sf[$row_code]['total']) );
                }
                $fp = $this->sf[$row_code]['total'];
                $pp = ($fp / $this->ns);
                $exp_pos = $pp * $pg;
                
                $pgp = 0;
                if ($fp > 0) {
                    $pgp = ($sf / $fp);
                }
                
                if ($pg > 0 && $pp > 0 && ($pg * (1-$pg)) / (($this->ns) * $pp) > 0) {
                    $z = ($pgp - $pg) / sqrt(($pg * (1-$pg)) / (($this->ns) * $pp) );
                }
                else {
                    $z = 0;
                }
//                if ($row_code === "P" && $col_code === "G") {
//                    echo "----" . ((0.333 - 0.278 ) / sqrt( (0.278 * (1 - 0.278)) / (127*0.236) )) . "---";
//                    echo "----" . ((0.33333333333333  - 0.27835051546392 ) / sqrt( (0.27835051546392  * (1 - 0.27835051546392)) / (127*0.23622047244094 ) )) . "---";
//                    print_r(array($pgp, $pg, $this->ns, $pp, $z));
//                }

                $z_row[$col_code] = $z;

                if ($z > 1.96) {
                    //_td.addClass('sign');

                    $sign_result[$seq_name] = $z;
                }   

                //_e++;
            }
            $z_table[$row_code] = $z_row;
        }
        
        $this->z_table["transitional_probability"] = $z_table;

        $this->sign_result["transitional_probability"] = $sign_result;
        }*/

    function cal_z_score_allison_liker() {
        $code_list = $this->code_list;
        $code_f = $this->code_f;
        $lag_list = $this->lag_list;
        $seq_f = $this->seq_f;
        $ns = $this->ns;
        
        $sign_result = array();

        $z_table = array();

        foreach ($code_list AS $row_code) {

            $cf = 0;
            if (isset($code_f[$row_code]) && is_int($code_f[$row_code])) {
                $cf = $code_f[$row_code];
            }

            $z_row = array();
            foreach ($lag_list AS $col_code) {

                //var _col_code = _code_list[_j];

                $seq_name = $row_code . $col_code;

                $sf = 0;

                if (isset($seq_f[$seq_name]) && is_int($seq_f[$seq_name])) {
                    $sf = $seq_f[$seq_name];
                }
                
                $fpg = $sf;

                //$exp_pos = $this->exp_pos_list[$row_code][$col_code];
                
                $fp = $this->sf[$row_code]['total'];
                
//                $fg = $this->sf["col_total"][$col_code];
//                if ($this->repeatable === true) {
//                    $pg = ($fg / $this->ns);
//                }
//                else {
//                    $pg = ($fg / ($this->ns - $fp) );
//                }
                $pg = $this->_calc_prop_targets($col_code, $row_code);
                
                $pp = ($fp / $this->ns);
                //$pt = $pp;
                //$exp_pos = $pp * $pg;
                
                
                if (( $fp * $pg * (1-$pg) * (1-$pp) ) > 0) {
                    /*if ($row_code === "C" && $col_code === "B") {
                        print_r(array(
                            "fpg" => $fpg,
                            "fp" => $fp,
                            "pg" => $pg,
                            "pp" => $pp
                        ));
                        }*/
                
                    
                    $z = ($fpg - ($fp * $pg)) / sqrt( $fp * $pg * (1-$pg) * (1-$pp) );
                    /*if ($row_code === "C" && $col_code === "B") {
                        print_r(array(
                            "fpg" => $fpg,
                            "fp" => $fp,
                            "pg" => $pg,
                            "pp" => $pp,
                            "母" => sqrt( $fp * $pg * (1-$pg) * (1-$pp) ),
                            "公" => ($fpg - ($fp * $pg)),
                            "z" => $z
                        ));
                        }*/
                    
                    // P->G: 0.76885500628616
                }
                else {
                    $z = 0;
                }
                //if ($row_code === "P" && $col_code === "G") {
                    //echo (10 - (30*0.278) ) / sqrt( 30 * 0.278 * (1-0.278) * (1-0.236) ) . '-------';
                    //echo (10 - (30*0.27835051546392 ) ) / sqrt( 30 * 0.27835051546392  * (1-0.27835051546392 ) * (1-0.23622047244094 ) ) . '-------';
                //    print_r(array($fpg, $fp, $pg, $fg, $pg, $pg, $pp, $z));
                //}

//                if (is_float($z) === FALSE) {
//                    $z = 0;
//                }

                $z_row[$col_code] = $z;

                if ($z > 1.96) {
                    $sign_result[$seq_name] = $z;
                }   

                //_e++;
            }
            $z_table[$row_code] = $z_row;
        }
        
        $this->z_table["allison_liker"] = $z_table;

        $this->sign_result["allison_liker"] = $sign_result;
    }
    
    /**
     * @deprecated since version 20160725 allison liker
     *//*
    function cal_z_score_allison_liker2() {
        $code_list = $this->code_list;
        $code_f = $this->code_f;
        $lag_list = $this->lag_list;
        $seq_f = $this->seq_f;
        $ns = $this->ns;
        
        $sign_result = array();

        $z_table = array();

        foreach ($code_list AS $row_code) {

            $cf = 0;

            if (is_int($code_f[$row_code])) {
                $cf = $code_f[$row_code];
            }

            $z_row = array();
            foreach ($lag_list AS $col_code) {

                //var _col_code = _code_list[_j];

                $seq_name = $row_code . $col_code;

                $sf = 0;

                if (isset($seq_f[$seq_name]) && is_int($seq_f[$seq_name])) {
                    $sf = $seq_f[$seq_name];
                }
                $fpg = $sf;

                //$exp_pos = $this->exp_pos_list[$row_code][$col_code];
                $fg = $this->sf["col_total"][$col_code];
                $fp = $this->sf[$row_code]['total'];
                if ($this->repeatable === true) {
                    $pg = ($fg / $this->ns);
                }
                else {
                    $pg = ($fg / ($this->ns - $fp) );
                }
                
                $pp = ($fp / $this->ns);
                //$pt = $pp;
                //$exp_pos = $pp * $pg;
                
                
                //$z = ($fpg - ($fp * $pg)) / sqrt( $fp * $pg * (1-$pg) * (1-$pp) );
                $pgp = ($sf / $fp);
                
                $z = ($pgp - $pg) / sqrt( ($pg * (1-$pg) * (1-$pp)) / ($this->ns * $pp) ) ;
                //if ($row_code === "P" && $col_code === "G") {
                //    print_r(array($fpg, $fp, $pg, $fg, $pg, $pg, $pp, $z));
                //}

//                if (is_float($z) === FALSE) {
//                    $z = 0;
//                }

                $z_row[$col_code] = $z;

                if ($z > 1.96) {
                    //_td.addClass('sign');

                    $sign_result[$seq_name] = $z;
                }   

                //_e++;
            }
            $z_table[$row_code] = $z_row;
        }
        
        $this->z_table["allison_liker2"] = $z_table;

        $this->sign_result["allison_liker2"] = $sign_result;
        }*/
    
    /**
     * @param {String|NULL} $target_model
     * @return array
     */
    function export_sign_result($target_model = NULL) {
        $export = array();
        foreach ($this->sign_result AS $model => $results) {
            if (count($results) > 0) {
                $ary = array();
                foreach ($results AS $seq => $z) {
                  $source = mb_substr($seq, 0, 1);
                  $target = mb_substr($seq, 1, 1);
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
        
        if (is_null($target_model) === FALSE) {
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
      foreach ($export AS $item) {
        $exportCSV[] = $item['source'].','.$item['target'].','.$item['label'].','.$item['value'].','.$item['frequency'];
      }
      return implode("\n", $exportCSV);
    }

    /**
     * @param {string} $table
     * @return string
     */
    static function table_draw($table, $show_sig = FALSE) {
        $html = '<table border="1">';
        
        $thead_th = array();
        
        $first_tr = true;
        $tbody = "<tbody>";
        foreach ($table AS $row_code => $row) {
            
            $tr = "<tr>";
            $tr .= "<th>" . $row_code . "</th>";
            
            if (is_array($row)) {
                foreach ($row AS $col_code => $cell) {
                    if ($first_tr === TRUE) {
                        $thead_th[] = $col_code;
                    }

                    if ($show_sig === TRUE && $cell > 1.96) {
                        $tr .= "<td style='color:red;'>" . $cell . "</td>";
                    }
                    else {
                        $tr .= "<td>" . $cell . "</td>";
                    }
                }
            }
            else {
                $cell = $row;
                if ($show_sig === TRUE && $cell > 1.96) {
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
        foreach ($thead_th AS $th) {
            $thead .= "<th>" . $th . "</th>";
        }
        $thead .= "</thead>";
        
        $html .= $thead . $tbody . "</table>";
        return $html;
    }
}   //  class sa {

if (!function_exists('mb_str_replace'))
{
   function mb_str_replace($search, $replace, $subject, &$count = 0)
   {
      if (!is_array($subject))
      {
         $searches = is_array($search) ? array_values($search) : array($search);
         $replacements = is_array($replace) ? array_values($replace) : array($replace);
         $replacements = array_pad($replacements, count($searches), '');
         foreach ($searches as $key => $search)
         {
            $parts = mb_split(preg_quote($search), $subject);
            $count += count($parts) - 1;
            $subject = implode($replacements[$key], $parts);
         }
      }
      else
      {
         foreach ($subject as $key => $value)
         {
            $subject[$key] = str_replace($search, $replace, $value, $count);
         }
      }
      return $subject;
   }
}
