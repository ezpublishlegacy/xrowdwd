{def $DWD_data = getDWDdata()
     $time = currentdate()
}

{if and(is_set($DWD_data), count($DWD_data)|gt(0) )}
    <div id="xrowdwd-container-{$block.id}" class="xrowdwd-container grey_corner float-break block">
        {if and( is_set( $block.name ), $block.name|ne(""))}
            <h2 class="xrowdwd_heading">{$block.name|wash()}</h2>
        {/if}
        <table border="0">
            <tr class="xrowdwd_day">
                {foreach $DWD_data as $key => $no_matter}
                    <td align="center" class="xrowdwd_day_cell">{sum( $time, mul( $key, 86400 ) )|datetime('custom','%D')}</td>
                {/foreach}
            </tr>
            <tr class="xrowdwd_icon">
                {foreach $DWD_data as $item}
                    <td align="center" class="xrowdwd_icon_cell">
                        <img src={concat("weather/d_", $item.img, "_b.png")|ezimage()} title="{$item.state|wash()}" />
                    </td>
                {/foreach}
            </tr>
            <tr class="xrowdwd_temperature">
                {foreach $DWD_data as $item}
                    <td align="center" class="xrowdwd_temperature_cell">{$item.temp|wash()}&deg;C</td>
                {/foreach}
            </tr>
        </table>
    </div>
{/if}

{undef $DWD_data}