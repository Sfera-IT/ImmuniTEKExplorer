<?php
//class TekExplorer_it extends IPlugin {
class TekExplorer_it 
{
    function do(&$current, $countryCode, $countryData)
    {
        $full = file_get_contents("https://www.immuni.italia.it/main.13bed95689cbd21052e2.js");
        $str = extractStringBetween($full, "e.exports=JSON.parse('","')"); 
        $os = jsonDecode($str);
        $full = str_replace("e.exports=JSON.parse('" . $str . "')", "",$full);
        $str = extractStringBetween($full, "e.exports=JSON.parse('","')"); 
        $misc = jsonDecode($str);
        $full = str_replace("e.exports=JSON.parse('" . $str . "')", "",$full);
        $str = extractStringBetween($full, "e.exports=JSON.parse('","')"); 
        $topo1 = jsonDecode($str);
        $full = str_replace("e.exports=JSON.parse('" . $str . "')", "",$full);
        $str = extractStringBetween($full, "e.exports=JSON.parse('","')"); 
        $topo2 = jsonDecode($str);
        $full = str_replace("e.exports=JSON.parse('" . $str . "')", "",$full);
        $str = extractStringBetween($full, "e.exports=JSON.parse('","')"); 
        $regions = jsonDecode($str); // Non va, non so perchè.    
        
        foreach($os as $k => $v)
        {
            $day = date('Y-m-d', strtotime($k));
            $current["days"][$day][$countryCode]["app_download_os_ios"] = $v["ios"];
            $current["days"][$day][$countryCode]["app_download_os_android"] = $v["android"];
            $current["days"][$day][$countryCode]["app_download_os_total"] = $v["total"];
        }

        foreach($misc as $k => $v)
        {
            $day = date('Y-m-d', strtotime($k));
            $current["days"][$day][$countryCode]["app_notifications_sent"] = $v["notifications"];
            $current["days"][$day][$countryCode]["app_positive_users"] = $v["positive_users"];
            $current["days"][$day][$countryCode]["app_contained_outbreaks"] = $v["contained_outbreaks"];
        }
    }
}