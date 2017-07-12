<?php
/*
Plugin Name: PPC Tracker WordPress Plugin
Plugin URI: 
Description: Detects ppc traffic and collects IP addresses.
Version: 2.0

 */

//Save IP Address to db
function PPCTracker()
{
    global $wpdb;
    $table_name = $wpdb->prefix . "ppc_tracker";
    ppc_tracker_install();
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return;
    }
    $referer = $_SERVER['HTTP_REFERER'];
    //echo $referer;
    if(!contains($referer,"www.googleadservices.com/pagead/aclk"))
    {
        return;
    }
    $ipAddress = get_client_ip();
    $dateCreated = date("Y-m-d H:i:s");
    $sql = "INSERT INTO $table_name(`IPAddress`,`DateCreated`) VALUES('$ipAddress','$dateCreated')";
    $wpdb->query($sql);
}
add_action("init","PPCTracker");


//Plugin Installed
function ppc_tracker_install()
{
	global $wpdb;
    $table_name = $wpdb->prefix . "ppc_tracker";
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql =  "CREATE TABLE `$table_name` (
       `Id`  int NOT NULL AUTO_INCREMENT ,
       `IPAddress`  varchar(100) NOT NULL ,
       `DateCreated`  datetime NOT NULL,PRIMARY KEY (`id`));";
        require_once(ABSPATH . "wp-admin/includes/upgrade.php");
        dbDelta($sql);
    }
    $table_name = $wpdb->prefix . "ppc_tracker_archived";
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql =  "CREATE TABLE `$table_name` (
       `Id`  int NOT NULL AUTO_INCREMENT ,
       `IPAddress`  varchar(100) NOT NULL ,
       `DateCreated`  datetime NOT NULL,PRIMARY KEY (`id`));";
        require_once(ABSPATH . "wp-admin/includes/upgrade.php");
        dbDelta($sql);
    }
    
}
function ppc_tracker_remove( )
{
	//delete table
}

register_activation_hook(__FILE__,'ppc_tracker_install');   
register_deactivation_hook( __FILE__, 'ppc_tracker_remove' ); 

add_action('admin_menu', 'display_ppc_tacker_menu');  


function display_ppc_tacker_menu()
{
    
    add_menu_page("PPC Tracker Management","PPC Tracker","administrator","ppc_tracker","ppc_tracker_html");
}

function export_csv($filename,$data) { 
    ob_clean();
    header("Content-type:text/csv"); 
    header("Content-Disposition:attachment;filename=".$filename); 
    //header('Cache-Control:must-revalidate,post-check=0,pre-check=0'); 
    //header('Expires:0'); 
    //header('Pragma:public'); 
    echo $data; 
    exit(0);
}

function ppc_tracker_html( )
{
    ppc_tracker_install();
    global $wpdb;
    $table_name = $wpdb->prefix . "ppc_tracker";
    
    if (isset($_POST["btnDownloadCountTable"]))
    {
        $sql = "SELECT IPAddress,Count(IPAddress) as Count FROM $table_name GROUP BY IPAddress HAVING Count(IPAddress) > 2";
        $csv_results = $wpdb->get_results($sql,ARRAY_A);
        $csv = array();
        $csv[] = "IPAddress,Visits";
        foreach ($csv_results as $item)
        {
            $csv[] = $item['IPAddress'].",".$item["Count"];
        }
        $data = join("\r\n",$csv);
        export_csv('ppc-tracker-' . date("YmdHis") . '.csv' , $data);
    }
    else if(isset($_POST["btnDownloadWholeTable"]))
    {
        $sql = "SELECT * FROM $table_name";
        $csv_results = $wpdb->get_results($sql,ARRAY_A);
        $csv = array();
        $csv[] = "ID,IPAddress,DateCreated";
        foreach ($csv_results as $item)
        {
            $csv[] = join(",",$item);
        }
        $data = join("\r\n",$csv);
        export_csv('ppc-tracker-whole' . date("YmdHis") . '.csv' , $data);
    }
    else if(isset($_POST["btnDownloadArchivedTable"]))
    {
        $table_name = $wpdb->prefix . "ppc_tracker_archived";
        $sql = "SELECT * FROM $table_name";
        $csv_results = $wpdb->get_results($sql,ARRAY_A);
        $csv = array();
        $csv[] = "ID,IPAddress,DateCreated";
        foreach ($csv_results as $item)
        {
            $csv[] = join(",",$item);
        }
        $data = join("\r\n",$csv);
        export_csv('ppc-tracker-archived' . date("YmdHis") . '.csv' , $data);
    }
    else if(isset($_POST["btnArchived"]))
    {
        $table_name_archived = $wpdb->prefix . "ppc_tracker_archived";
        $sql1 = "INSERT INTO $table_name_archived (IPAddress,DateCreated) SELECT IPAddress,DateCreated FROM $table_name WHERE DATEDIFF(NOW(),DateCreated) > 30";
        $sql2 = "DELETE FROM $table_name WHERE DATEDIFF(NOW(),DateCreated) > 30";
        $wpdb->query($sql1);
        $wpdb->query($sql2);
    }
    
    $results = $wpdb->get_results( "SELECT IPAddress,Count(IPAddress) as Count FROM $table_name GROUP BY IPAddress HAVING Count(IPAddress) > 2",ARRAY_A);  
    
    
?>
<div class="wrap">
    <h2>PPC Tracker Management</h2>
    <form action="/wp-admin/admin.php?page=ppc_tracker" method="post">
        <div style="padding: 5px 0px;">
            <input type="submit" name="btnDownloadCountTable" class="button" value="Download as CSV (current table)">
            <input type="submit" name="btnDownloadWholeTable" class="button" value="Download as CSV (whole table)">
            <input type="submit" name="btnDownloadArchivedTable" class="button" value="Download as CSV (archived table)">
            <input type="submit" name="btnArchived" onclick="return confirm('Are you sure you want to move data that older thant 30 days to archive table?')" class="button" value="Archive (older than 30 days)">
        </div>
        <table border="0" class="wp-list-table widefat fixed pages">
            <thead>
                <tr>
                    <th class="manage-column column-title">IP Address</th>
                    <th class="manage-column column-title">Visits</th>
                </tr>
            </thead>
            <tbody class="the-list">
                <?php foreach($results as $item):?>
                <tr>
                    <td><?php echo $item["IPAddress"] ?></td>
                    <td><?php echo $item["Count"] ?></td>
                </tr>
                <?php endforeach;?>
            </tbody>
        </table>
    </form>

</div>


<?php
}




function contains($str,$contain)
{
    if(stripos($contain,"|") !== false)
    {
        
        $s = preg_split('/[|]+/i',$contain);
        $len = sizeof($s);
        for($i=0;$i < $len;$i++)
        {
            if(stripos($str,$s[$i]) !== false)
            {
                return(true);
            }
        }
    }
    if(stripos($str,$contain) !== false)
    {
        return(true);
    }
    return(false);
}

function get_client_ip() {
    $ipaddress = '';
    if ($_SERVER['HTTP_CLIENT_IP'])
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if($_SERVER['HTTP_X_FORWARDED_FOR'])
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if($_SERVER['HTTP_X_FORWARDED'])
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if($_SERVER['HTTP_FORWARDED_FOR'])
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if($_SERVER['HTTP_FORWARDED'])
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if($_SERVER['REMOTE_ADDR'])
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';

    return $ipaddress; 
}

?>