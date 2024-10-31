<?php
namespace Recommender;

function makeMinutes($s){
    $s = intval($s);
    $m = intval($s/60);
    $s -= $m*60;
    $h = intval($m/60);
    $m -= $h*60;
    return strval($h).' : '.strval($m)."'".' : '.strval($s).'"';
}
$user_columns = [
    "identifier" => esc_html__("user id", "robera-recommender"),
    "last_interaction_time" => esc_html__("last interaction time", "robera-recommender"),
//    "last_interaction_type" => esc_html__("last interaction type", "robera-recommender"),
    "total_purchase" => esc_html__("total purchase", "robera-recommender"),
    "num_purchase" => esc_html__("num purchase", "robera-recommender"),
    "presence" => esc_html__("time on site", "robera-recommender"),
    "cart_value" => esc_html__("cart value", "robera-recommender")
];
$tooltips = [
    "identifier" => null,
    "last_interaction_time" => esc_html__("The very last time that the person had an interaction", "robera-recommender"),
//    "last_interaction_type" => null,
    "total_purchase" => esc_html__("Total value of user's purchases", "robera-recommender"),
    "num_purchase" => esc_html__("Total number of user's purchased order items", "robera-recommender"),
    "presence" => esc_html__("Total time spent by user in your website", "robera-recommender"),
    "cart_value" => null
];
$anon_columns = [
    "identifier" => esc_html__("anonymous id", "robera-recommender"),
    "last_interaction_time" => esc_html__("last interaction time", "robera-recommender"),
//    "last_interaction_type" => esc_html__("last interaction type", "robera-recommender"),
    "total_purchase" => esc_html__("total purchase", "robera-recommender"),
    "num_purchase" => esc_html__("num purchase", "robera-recommender"),
    "presence" => esc_html__("time on site", "robera-recommender")
];
$from = esc_html__('From', 'robera-recommender');
$to = esc_html__('To', 'robera-recommender');
$args = array(
    'limit' => '-1',
    'return' => 'ids'
);
$product_ids = wc_get_products($args);
$last_interaction = [
    isset($_GET['last_interaction_from'])?$_GET['last_interaction_from']:'',
    isset($_GET['last_interaction_to'])?$_GET['last_interaction_to']:''
];
$created_at = [
    isset($_GET['created_at_from'])?$_GET['created_at_from']:'',
    isset($_GET['created_at_to'])?$_GET['created_at_to']:''
];
$total_purchase = [
    isset($_GET['total_purchase_from'])?$_GET['total_purchase_from']:'',
    isset($_GET['total_purchase_to'])?$_GET['total_purchase_to']:''
];
$num_purchase = [
    isset($_GET['num_purchase_from'])?$_GET['num_purchase_from']:'',
    isset($_GET['num_purchase_to'])?$_GET['num_purchase_to']:''
];
$special_item = (isset($_GET['special_item']))?$_GET['special_item']:'';
?>
<script>
    <?php if($server_data['permission']): ?>
    window.onload = function () {
        var chart = new CanvasJS.Chart("chartContainer", {
            theme: "light2",
            animationEnabled: true,
            title: {
                text: "<?php echo esc_html__("Total Users : ", 'robera-recommender').$num_all_users; ?>",
                fontFamily: 'rbr-font-family'
            },
            legend: {
                verticalAlign: "top",
                horizontalAlign: "right",
                fontFamily: 'rbr-font-family'
            },
            data: [{
                type: "pie",
                indexLabelFontSize: 14,
                startAngle: 40,
                radius: 200,
                showInLegend: true,
                legendText: "{label}",
                indexLabel: "{label} - {y}",
                yValueFormatString: "###0.0\"%\"",
                click: explodePie,
                dataPoints: [
                    {y: <?php echo $anonymous_users_percentage ?>, label: "<?php esc_html_e("Anonymous Users", "robera-recommender") ?>", fontFamily: 'rbr-font-family'},
                    {y: <?php echo $registered_users_percentage ?>, label: "<?php esc_html_e("Registered Users", "robera-recommender") ?>", fontFamily: 'rbr-font-family'}
                ],
                fontFamily: 'rbr-font-family'
            }]
        });
        chart.render();

        function explodePie(e) {
            for (var i = 0; i < e.dataSeries.dataPoints.length; i++) {
                if (i !== e.dataPointIndex)
                    e.dataSeries.dataPoints[i].exploded = false;
            }
        }

    }
    <?php endif; ?>
</script>
<div class="semantic" style="font-family: rbr-font-family; !important;">
    <div class="ui segment">
        <?php if($server_data['msg']): ?>
            <div class="ui segment <?php echo $server_data['permission']?'yellow':'red' ?>">
                <h3 style="font-family: rbr-font-family"><?php esc_html_e('Attention', 'robera-recommender'); ?></h3>
                <div style="font-family: rbr-font-family" class="description"><?php echo $server_data['msg']; ?></div>
            </div>
        <?php endif; ?>
        <?php if($server_data['permission']): ?>
            <div class="ui vertical segments" style="margin-right: 10px; margin-left: 10px;">
                <div class="ui segment teal">
                    <div id="chartContainer" style="height: 150px; width: 100%; margin-top: 10px;"></div>
                </div>
                <div class="ui horizontal segments">
                    <div class="ui segment teal" style="width: 20%">
                        <i class="large truck icon"></i>
                        <div class="title"><?php esc_html_e("number of purchases", "robera-recommender") ?></div>
                        <div class="description"><?php echo $overview['num_purchase_interactions'] ?></div>
                    </div>
                    <div class="ui segment teal" style="width: 20%">
                        <i class="large shopping cart icon"></i>
                        <div class="title"><?php esc_html_e("total value of purchases", "robera-recommender") ?></div>
                        <div class="description"><?php echo wc_price($overview['total_purchase']) ?></div>
                    </div>
                    <div class="ui segment teal" style="width: 20%">
                        <i class="large shopping cart icon"></i>
                        <div class="title"><?php esc_html_e("average value of purchases", "robera-recommender") ?></div>
                        <div class="description"><?php echo wc_price($overview['avg_purchase']) ?></div>
                    </div>
                    <div class="ui segment teal" style="width: 20%">
                        <i class="hourglass icon"></i>
                        <div class="title"><?php esc_html_e("average of users' views", "robera-recommender") ?></div>
                        <div class="description"><?php echo $overview['avg_user_time'] ?></div>
                    </div>
                    <div class="ui segment teal" style="width: 20%">
                        <i class="hourglass icon outline"></i>
                        <div class="title"><?php esc_html_e("total view average time", "robera-recommender") ?></div>
                        <div class="description"><?php echo $overview['avg_view_time'].' '.esc_html__("seconds", "robera-recommender") ?></div>
                    </div>
                </div>
            </div>
            <div>
                <form action="admin.php" method="GET">
                    <div class="ui grid" style="margin:10px;">
                        <div class="sixteen wide column">
                            <h4 style="font-family: rbr-font-family">
                                <?php
                                if ($tab == 'registered')
                                    esc_html_e('Filter Users', 'robera-recommender');
                                else
                                    esc_html_e('Filter Anonymous Users', 'robera-recommender');
                                ?>
                            </h4>
                            <button type="button" class="ui button" style="font-family: rbr-font-family; height: auto"
                                    id="rbr-last-interaction-filter"><?php esc_html_e("Last Interaction time", "robera-recommender") ?></button>
                            <button type="button" class="ui button" style="font-family: rbr-font-family; height: auto"
                                    id="rbr-created-at-filter"><?php esc_html_e("First Interaction time", "robera-recommender") ?></button>
                            <button type="button" class="ui button" style="font-family: rbr-font-family; height: auto"
                                    id="rbr-total-purchase-filter"><?php esc_html_e("Total Purchase amount", "robera-recommender") ?></button>
                            <button type="button" class="ui button" style="font-family: rbr-font-family; height: auto"
                                    id="rbr-num-purchase-filter"><?php esc_html_e("Num of purchases", "robera-recommender") ?></button>
                            <button type="button" class="ui button" style="font-family: rbr-font-family; height: auto"
                                    id="rbr-special-item-filter"><?php esc_html_e("Product purchased", "robera-recommender") ?></button>
                            <input type="submit" class='positive ui button' id="filter_submit_button" style="font-family: rbr-font-family;height: unset"
                                   value="<?php esc_html_e('Filter', 'robera-recommender') ?>"/>
                            <a href="admin.php?page=recommender_users<?php echo '&tab='.$tab; ?>">
                                <button type="button" class="ui button black" style="font-family: rbr-font-family; height: auto"
                                        id="rbr-remove-filter"><?php esc_html_e("Remove Filters", "robera-recommender") ?>
                                </button>
                            </a>
                        </div>
                        <div class="sixteen wide column" style="display:none" id="last-interaction-form">
                            <div class="ui form">
                                <div class="inline fields">
                                    <label><?php echo $from ?></label>
                                    <div class="field">
                                        <input type="date" value="<?php echo $last_interaction[0]?>" id="last_interaction_from" name="last_interaction_from" pattern="\d{4}-\d{2}-\d{2}">
                                    </div>
                                    <label><?php echo $to ?></label>
                                    <div class="field">
                                        <input type="date" value="<?php echo $last_interaction[1]?>" id="last_interaction_to" name="last_interaction_to" pattern="\d{4}-\d{2}-\d{2}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="sixteen wide column" style="display:none" id="created-at-form">
                            <div class="ui form">
                                <div class="inline fields">
                                    <label><?php echo $from ?></label>
                                    <div class="field">
                                        <input type="date" value="<?php echo $created_at[0]?>" id="created_at_from" name="created_at_from" pattern="\d{4}-\d{2}-\d{2}">
                                    </div>
                                    <label><?php echo $to ?></label>
                                    <div class="field">
                                        <input type="date" value="<?php echo $created_at[1]?>" id="created_at_to" name="created_at_to" pattern="\d{4}-\d{2}-\d{2}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="sixteen wide column" style="display:none" id="total-purchase-form">
                            <div class="ui form">
                                <div class="inline fields">
                                    <label><?php echo $from ?></label>
                                    <div class="field">
                                        <div class="ui right labeled input">
                                            <input type="number" style="height: unset" value="<?php echo $total_purchase[0]?>" id="total_purchase_from" name="total_purchase_from" min="0">
                                            <div class="ui basic label">
                                                <?php echo get_woocommerce_currency_symbol(); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <label><?php echo $to ?></label>
                                    <div class="field">
                                        <div class="ui right labeled input">
                                            <input type="number" style="height: unset" value="<?php echo $total_purchase[1]?>" id="total_purchase_to" name="total_purchase_to" min="0">
                                            <div class="ui basic label">
                                                <?php echo get_woocommerce_currency_symbol(); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="sixteen wide column" style="display:none" id="num-purchase-form">
                            <div class="ui form">
                                <div class="inline fields">
                                    <label><?php echo $from ?></label>
                                    <div class="field">
                                        <input type="number" value="<?php echo $num_purchase[0]?>" id="num_purchase_from" name="num_purchase_from" min="0">
                                    </div>
                                    <label><?php echo $to ?></label>
                                    <div class="field">
                                        <input type="number" value="<?php echo $num_purchase[1]?>" id="num_purchase_to" name="num_purchase_to" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="sixteen wide column" style="display:none" id="special-item-form">
                            <div class="ui form">
                                <div class="field" style="margin: 10px">
                                    <label><?php esc_html_e('Product', 'robera-recommender'); ?></label>
                                    <select name='special_item' class="ui search dropdown" id="rbr-select" value="<?php echo $special_item ?>">
                                        <option value="">Select Product</option>
                                        <?php foreach ($product_ids as $id): ?>
                                            <option value="<?php echo $id ?>" <?php echo ($id==$special_item)?'selected':''; ?>>
                                                <?php echo wc_get_product($id)->get_title(); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <input type="text" value="recommender_users" class="rbr-real" name="page" hidden>
                        <input type="text" value="<?php echo $tab ?>" class="rbr-real" name="tab" hidden>
                    </div>
                </form>
            </div>
            <div class="ui top attached tabular menu" style="fontFamily: 'rbr-font-family'">
                <a class="item <?php if($tab=='registered'): echo "active"; endif;?>" style="font-family: rbr-font-family" href="<?php echo (($tab=='registered')?$link.'?'.http_build_query($queries):$parent_url.'&tab=registered&user_page=1'); ?>">
                    <?php esc_html_e("Registered Users", "robera-recommender") ?>
                </a>
                <a class="item <?php if($tab=='anonymous'): echo "active"; endif;?>" style="font-family: rbr-font-family" href="<?php echo (($tab=='anonymous')?$link.'?'.http_build_query($queries):$parent_url. '&tab=anonymous&user_page=1'); ?>">
                    <?php esc_html_e("Anonymous Users", "robera-recommender") ?>
                </a>
            </div>
            <div class="ui bottom attached segment">
                <?php if(empty($users)): ?>
                    <h4 style="font-family: rbr-font-family"><?php esc_html_e('Not Found', 'robera-recommender');?></h4>
                <?php endif; ?>
                <?php $columns = ($tab=='registered')? $user_columns:$anon_columns; ?>
                <table class="ui celled padded selectable table green form-table">
                    <thead>
                    <?php foreach ($columns as $key => $value): ?>
                        <th style="font-family: rbr-font-family" <?php if($tooltips[$key]):?>data-tooltip="<?php echo $tooltips[$key]; ?>" <?php endif; ?>>
                            <?php echo $value ?>
                        </th>
                    <?php endforeach; ?>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="selectable"><a href="<?php echo $parent_url.'&'.http_build_query(['user_id'=>$user['identifier'], 'user_page'=>'1']); ?>"><?php echo ($tab=='registered')?get_userdata($user['identifier'])->user_login:esc_html__('Anonymous User', 'robera-recommender').' '.$user['name']; ?></a></td>
                            <?php foreach ($columns as $key => $value): ?>
                                <?php if($key == 'identifier'): continue; endif; ?>
                                <?php if($key == 'total_purchase' || $key == 'cart_value'): ?>
                                    <td><?php echo wc_price($user[$key]) ?></td>
                                <?php elseif($key == 'last_interaction_type'): ?>
                                    <td style="font-family: rbr-font-family"><?php echo $interaction_translation[$user[$key]] ?></td>
                                <?php elseif($key == 'last_interaction_time'): ?>
                                    <td style="font-family: rbr-font-family"><?php echo ($user[$key]=='no interaction')?$interaction_translation[$user[$key]]:$user[$key] ?></td>
                                <?php elseif($key == 'presence'): ?>
                                    <td style="font-family: rbr-font-family" dir="ltr"><?php echo makeMinutes($user[$key]);//.' '.esc_html__('seconds', 'robera-recommender'); ?></td>
                                <?php else: ?>
                                    <td><?php echo $user[$key] ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="9">
                            <div class="ui right floated pagination menu" style="padding: unset;">
                                <?php $queries['user_page'] = max(1, $current_user_page - 1) ?>
                                <a class="icon item <?php if ($current_user_page == 1 || empty($users)): echo "disabled"; endif; ?>" <?php if ($current_user_page != 1 && !empty($users)): echo 'href="' . $link.'?'.http_build_query($queries).'"'; endif; ?>>
                                    <i class="<?php echo (is_rtl())?"right":"left"; ?> chevron icon"></i>
                                </a>
                                <?php $queries['user_page'] = 1 ?>
                                <?php if ($current_user_page > 3): ?>
                                    <a class="item"
                                       href="<?php echo $link.'?'.http_build_query($queries) ?>">1</a>
                                <?php endif; ?>
                                <?php if ($current_user_page > 4): ?>
                                    <a class="item disabled">...</a>
                                <?php endif; ?>
                                <?php for ($i = max(1, $current_user_page - 2); $i <= min($current_user_page + 2, $users_page_num); $i++): ?>
                                    <?php $queries['user_page'] = $i ?>
                                    <a class="item <?php if ($i == $current_user_page): echo "active"; endif; ?>"
                                       href="<?php echo $link.'?'.http_build_query($queries); ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                                <?php if ($current_user_page + 3 < $users_page_num): ?>
                                    <a class="item disabled">...</a>
                                <?php endif; ?>
                                <?php if ($current_user_page + 2 < $users_page_num): ?>
                                    <?php $queries['user_page'] = $users_page_num ?>
                                    <a class="item"
                                       href="<?php echo $link.'?'.http_build_query($queries); ?>"><?php echo $users_page_num; ?></a>
                                <?php endif; ?>
                                <?php $queries['user_page'] = min($users_page_num, $current_user_page + 1); ?>
                                <a class="icon item <?php if ($current_user_page == $users_page_num || empty($users)): echo "disabled"; endif; ?>" <?php if ($current_user_page != $users_page_num && !empty($users)): echo 'href="' . $link.'?'.http_build_query($queries).'"'; endif; ?>>
                                    <i class="<?php echo (is_rtl())?"left":"right"; ?> chevron icon"></i>
                                </a>
                            </div>
                        </th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>