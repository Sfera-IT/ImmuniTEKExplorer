<?php
//class TekExplorer_it extends IPlugin {
class TekExplorer_it 
{
    function do(&$current, $countryCode, $countryData)
    {
        $dataAndamentoDownload = jsonDecode(file_get_contents("https://raw.githubusercontent.com/immuni-app/immuni-dashboard-data/master/dati/andamento-download.json"));

        foreach($dataAndamentoDownload as $item)
        {
            $day = date('Y-m-d', strtotime($item["data"]));
            $current["days"][$day][$countryCode]["app_download_os_ios"] = $item["ios"];
            $current["days"][$day][$countryCode]["app_download_os_android"] = $item["android"];
            $current["days"][$day][$countryCode]["app_download_os_ios_android"] = $item["ios_android"];
        }

        $dataAndamentoDatiNazionali = jsonDecode(file_get_contents("https://raw.githubusercontent.com/immuni-app/immuni-dashboard-data/master/dati/andamento-dati-nazionali.json"));

        foreach($dataAndamentoDatiNazionali as $item)
        {
            $day = date('Y-m-d', strtotime($item["data"]));            
            $current["days"][$day][$countryCode]["app_notifications_sent"] = $item["notifiche_inviate"];
            $current["days"][$day][$countryCode]["app_positive_users"] = $item["utenti_positivi"];            
            $current["days"][$day][$countryCode]["app_notifications_sent_total"] = $item["notifiche_inviate_totali"];
            $current["days"][$day][$countryCode]["app_positive_users_total"] = $item["utenti_positivi_totali"];            
        }
    }
}