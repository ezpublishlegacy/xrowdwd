<?php
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: xrowdwd Extension
// SOFTWARE RELEASE: 1.0-0
// COPYRIGHT NOTICE: Copyright (C) 1999-2011 eZ Systems AS
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
// This program is free software; you can redistribute it and/or
// modify it under the terms of version 2.0 of the GNU General
// Public License as published by the Free Software Foundation.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of version 2.0 of the GNU General
// Public License along with this program; if not, write to the Free
// Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
// MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

class xrowDWDTemplateOperators
{
    function xrowDWDTemplateOperators()
    {
    }

    function operatorList()
    {
        return array( 'getDWDdata' );
    }

    function namedParameterPerOperator()
    {
        return true;
    }

    function namedParameterList()
    {
        return array( 'getDWDdata' => array( 'city' => array( 'type' => 'string',
                                                              'required' => false,
                                                              'default' => '' ) ) );
    }

    function modify( $tpl, $operatorName, $operatorParameters, &$rootNamespace, &$currentNamespace, &$operatorValue, &$namedParameters )
    {
        // disabled till implemented asyncron
        return array();

        eZDebug::accumulatorStart('dwd_data', 'Deutscher Wetter Dienst (DWD)', 'Fetch data'); 
        $xrowdwdini  = eZINI::instance('xrowdwd.ini');
        $remote_content = array();
        
        if( $xrowdwdini->hasVariable('xrowDWDSettings', 'AuthUserName') && $xrowdwdini->hasVariable('xrowDWDSettings', 'AuthPassword'))
        {
            $login_name = $xrowdwdini->variable('xrowDWDSettings', 'AuthUserName');
            $login_pw = $xrowdwdini->variable('xrowDWDSettings', 'AuthPassword');
        }
        
        $city = $namedParameters['city'];
        if ( isset($namedParameters['city']) AND $namedParameters['city'] != "" )
        {
            $city = $namedParameters['city'];
        }
        
        if( $city == "" && $xrowdwdini->hasVariable('xrowDWDSettings', 'FallbackCity'))
        {
            $city = $xrowdwdini->variable('xrowDWDSettings', 'FallbackCity');
        }
        
        $day = date("d.m.y");

        foreach ( $xrowdwdini->variable('xrowDWDSettings', 'URLs') as $key => $url )
        {
            if ( function_exists( 'curl_init' ) )
            {
                $curl_is_set = true;
                $ch = curl_init();
                curl_setopt( $ch, CURLOPT_URL, $url );
                curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt( $ch, CURLOPT_HEADER, 0 );
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
                curl_setopt( $ch, CURLOPT_TIMEOUT, 5 );
                curl_setopt( $ch, CURLOPT_ENCODING, "UTF-8" );
                if( $login_name && $login_pw ){
                    curl_setopt($ch, CURLOPT_USERPWD, $login_name . ':' . $login_pw);
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                }

                $temp_content = curl_exec( $ch );
                $info = curl_getinfo( $ch );

                if ( $info['http_code'] != 226 )
                {
                    $remote_content[$key] = false;
                    eZDebug::writeError( "URL ($url) is not available(Response Code: " . $info['http_code'] . " ) ", __METHOD__ );
                }
                else
                {
                    eZDebug::writeDebug( "URL ($url) accessed", __METHOD__ );
                }
                curl_close( $ch );
            }

            if ( !isset( $remote_content[$key] ) )
            {
                $pattern = "/[^\\n]*" . $city . "[^\\n]*/";
                preg_match_all($pattern, utf8_encode($temp_content), $town_line);

                $db = eZDB::instance();
                $result_set = $db->arrayQuery("SELECT * FROM dwd_archive WHERE day_clean = '$day';");

                if(isset($town_line[0][0]))
                {
                    
                    $temp_content = preg_split('/  +/', $town_line[0][0]);
                    $remote_content[$key]["temp"] = $temp_content[1];
                    $temp_state = $temp_content[2];

                    //WE ASSUME THAT THE FIRST FOREACHED ELEMENT WILL BE "TODAY"
                    if( $key === 0)
                    {
                        if ( count($result_set) === 0 )
                        {
                            $db->begin();
                            $db->arrayQuery("INSERT INTO dwd_archive ( day_clean, write_ts, temperature, state ) VALUES ( '$day' , " . time() . ", '" . $remote_content[$key]["temp"] . "', '$temp_state' );");
                            $db->commit();
                        }
                        else
                        {
                            if ( $result_set[0]["temperature"] != $remote_content[$key]["temp"] OR $result_set[0]["state"] != $temp_state )
                            {
                                $db->begin();
                                $db->arrayQuery("UPDATE dwd_archive SET temperature = '" . $remote_content[$key]["temp"] . "', state = '$temp_state' WHERE day_clean = '$day' ;");
                                $db->commit();
                            }
                        }
                    }
                }
                else
                {
                    //WE ASSUME THAT THE DATA IS NOT AVAILABLE SO FALL BACK TO THE DB!
                    $remote_content[$key]["temp"] = $result_set[0]["temperature"];
                    $temp_state = $result_set[0]["state"];
                }
                
                //state mapping for the images
                if ( in_array( $temp_state, array("wolkenlos")  ) )
                {
                    $remote_content[$key]["img"] = 0;
                }
                else if ( in_array( $temp_state, array("gering bewölkt", "heiter")  ) )
                {
                    $remote_content[$key]["img"] = 1;
                }
                else if ( in_array( $temp_state, array("bewölkt", "leicht bewölkt")  ) )
                {
                    $remote_content[$key]["img"] = 2;
                }
                else if ( in_array( $temp_state, array("bedeckt")  ) )
                {
                    $remote_content[$key]["img"] = 3;
                }
                else if ( in_array( $temp_state, array("Nebel", "Dunst oder flacher Nebel", "in Wolken")  ) )
                {
                    $remote_content[$key]["img"] = 4;
                }
                else if ( in_array( $temp_state, array("leichter Regen", "Regenschauer")  ) )
                {
                    $remote_content[$key]["img"] = 5;
                }
                else if ( in_array( $temp_state, array("Regen")  ) )
                {
                    $remote_content[$key]["img"] = 6;
                }
                else if ( in_array( $temp_state, array( "leichter Schneefall", "Schneefall", "Schneefegen", "Schneeschauer", "Graupelschauer", "Hagelschauer", "Schneeregen", "Schneeregenschauer", "leichter Schneeregen" )  ) )
                {
                    $remote_content[$key]["img"] = 7;
                }
                else if ( in_array( $temp_state, array( "kein signifikantes Wetter" )  ) )
                {
                    $remote_content[$key]["img"] = 8;
                }
                else if ( in_array( $temp_state, array( "schweres Gewitter", "starkes Gewitter", "Gewitter" )  ) )
                {
                    $remote_content[$key]["img"] = 9;
                }
                else if ( in_array( $temp_state, array( "Sandsturm" )  ) )
                {
                    $remote_content[$key]["img"] = 14;
                }
                else if ( in_array( $temp_state, array( "kräftiger Regenschauer" )  ) )
                {
                    $remote_content[$key]["img"] = 15;
                }
                else if ( in_array( $temp_state, array( "Glatteisbildung", "kräftiger Regen" )  ) )
                {
                    $remote_content[$key]["img"] = 16;
                }
                else if ( in_array( $temp_state, array( "kräftiger Hagelschauer", "kräftiger Schneeregen", "kräftiger Graupelschauer", "kräftiger Schneeschauer", "kräftiger Schneeregenschauer", "kräftiger Schneefall" )  ) )
                {
                    $remote_content[$key]["img"] = 17;
                }

                $remote_content[$key]["state"] = $temp_state;

            }
        }
        eZDebug::accumulatorStop('dwd_data');
        $operatorValue = $remote_content;
    }
}
?>
