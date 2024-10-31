<?php
namespace Recommender;
$statistics = [
    "on_user" => esc_html__("Recommendations on user Statistics", "robera-recommender"),
    "on_item" => esc_html__("Related Items Recommendation's Statistics", "robera-recommender")
];

$rec_tooltip = [
    "on_item" => esc_html__("Number of related products recommendation blocks shown to users.", "robera-recommender"),
    "on_user" => esc_html__("Number of recommendations on user block shown to users", "robera-recommender")
];
if (empty($data))
    $color = 'gray';
else
    $color = 'blue active';
//    $color = ($data["state"]=='trained')?'green':'blue active';
?>
<div class="semantic" style="font-family: rbr-font-family">
    <div class="ui container" style="margin-top: 1rem">
        <div class="ui segment">
            <!div class="ui segment" style="background-color: #F2F2F2; width: max-content">
            <?php if($server_data['msg']): ?>
                <div class="ui segment <?php echo $server_data['permission']?'yellow':'red' ?>">
                    <h3 style="font-family: rbr-font-family"><?php esc_html_e('Attention', 'robera-recommender'); ?></h3>
                    <div style="font-family: rbr-font-family" class="description"><?php echo $server_data['msg']; ?></div>
                </div>
            <?php endif; ?>
            <?php if($server_data['permission']): ?>
            <div class="ui raised segment" style="display: inline-table; background-color: #f3f4f5">
                <div style="display: inline-flex; margin: 10px;">
                    <?php if (empty($data)): ?>
                        <img src="<?php echo plugins_url('../static/not-connected.png', __FILE__) ?>" width=50em
                             style="margin: 0.5em"></img>
                    <?php else: ?>
                        <img src="<?php echo plugins_url('../static/RooBeRah_Logo-04.svg', __FILE__) ?>" width=50em
                             style="margin: 0.5em"></img>
                    <?php endif; ?>
                    <div class="content" style="margin: 10px;">
                        <div id="robera-desc"
                             class="description"><?php esc_html_e("Robera's State", "robera-recommender") ?></div>
                        <?php if (empty($data)): ?>
                            <div class="title robera-tooltip" style="font-family: rbr-font-family;"
                                 data-tooltip="<?php esc_html_e("Refresh the page to try again.", "robera-recommender") ?>"><?php echo esc_html_e("Connection to Robera failed", "robera-recommender") ?></div>
                        <?php elseif ($data["state"] == "trained"): ?>
                            <div class="title robera-tooltip" style="color: #21ba45;font-family: rbr-font-family;"
                                 data-tooltip="<?php echo $tooltip_translation[$data["state"]] ?>"><?php echo $translation[$data["state"]] ?></div>
                        <?php else: ?>
                            <div class="title robera-tooltip" style="font-family: rbr-font-family; color: #4183c4"
                                 data-tooltip="<?php echo $tooltip_translation[$data["state"]] ?>"><?php echo $translation[$data["state"]] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!(empty($data) || $data['state']=='trained')): ?>
                    <div class="ui progress <?php echo $color ?> rbr-progress" style="width: 100%;height: auto;overflow: initial;">
                        <div class="bar rbr-progress" style="width: 0%">
                            <div class="progress rbr-progress" style="background: transparent">0%</div>
                        </div>
                        <div class="label rbr-progress" style="color: gray">Processing</div>
                    </div>
                <?php endif; ?>
            </div>
            <!/div>
            <h1 class="ui header" style="font-family: rbr-font-family;"><?php esc_html_e('Robera Statistics', "robera-recommender"); ?></h1>
            <form action="admin.php" method="GET">
                <div class="ui grid">
                    <div class="sixteen wide column">
                        <button class="ui button active" style="font-family: rbr-font-family; height: auto"
                                id="rbr-seven-days-filter"><?php esc_html_e("Last 7 Days", "robera-recommender") ?></button>
                        <button class="ui button active" style="font-family: rbr-font-family; height: auto"
                                id="rbr-last-month-filter"><?php esc_html_e("Last Month", "robera-recommender") ?></button>
                        <button type="button" class="ui button active" style="font-family: rbr-font-family; height: auto"
                                id="rbr-custom-filter"><?php esc_html_e("Custom", "robera-recommender") ?></button>
                        <?php if (!get_option('recommender_api_target_orders', 0)): ?>
                            <div
                                    class="ui button rbr-sync"
                                    style="font-family: rbr-font-family;height: unset"
                            >
                                <?php echo esc_html_e('Sync Data', 'robera-recommender') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="sixteen wide column" style="display:<?php if ($filter_type != "rbr-custom-filter"): echo "none"; endif;?>" id="filter-form">
                        <div class="ui form">
                            <div class="inline fields">
                                    <label>From</label>
                                    <div class="field">
                                        <input type="date" id="from_date" name="from_date" pattern="\d{4}-\d{2}-\d{2}">
                                    </div>
                                    <label>To</label>
                                    <div class="field">
                                        <input type="date" id="to_date" name="to_date" pattern="\d{4}-\d{2}-\d{2}">
                                    </div>
                                <input type="submit" class='positive ui button' id="filter_submit_button"
                                       style="font-family: rbr-font-family"
                                       value="<?php echo esc_html_e('Filter', 'robera-recommender') ?>"/>
                            </div>
                            <input type="text" value="recommender_settings" name="page" hidden>
                            <input type="text" value="" id="filter_type" name="filter_type" hidden>
                        </div>
                    </div>
                </div>
            </form>
            <?php foreach ($statistics as $key => $value): ?>
                <h3 class="ui header" style="font-family: rbr-font-family">
                    <?php echo $value ?>
                </h3>
                <div class="ui steps" data-tooltip="<?php echo $rec_tooltip[$key]; ?>">
                    <div class="step" style="width: 16em">
                        <div class="title"><?php echo empty($data) ? "..." : intval($data[$key]["num_recommendations"]) ?></div>
                        <div class="description"><?php esc_html_e("Recommendation blocks", "robera-recommender") ?></div>
                    </div>
                </div>
                <div class="ui steps"
                     data-tooltip="<?php esc_html_e("Number of Clicks on the items of the Recommendation Block. Click on any item of the block will count.", "robera-recommender") ?>">
                    <div class="step" style="width: 16em" data-tooltip="<>">
                        <div class="title"><?php echo empty($data) ? "..." : $data[$key]["num_clicks"] ?></div>
                        <div class="description"><?php esc_html_e("Clicks", "robera-recommender") ?></div>
                    </div>
                </div>
                <div class="ui steps"
                     data-tooltip="<?php esc_html_e("Shows the number of bought items from Robera’s recommended items. If a person buys 2 of one product, it will count 2.", "robera-recommender") ?>">
                    <div class="step" style="width: 16em">
                        <div class="title"><?php echo empty($data) ? "..." : $data[$key]["num_bought"] ?></div>
                        <div class="description"><?php esc_html_e("Bought Items", "robera-recommender") ?></div>
                    </div>
                </div>
                <div class="ui steps"
                     data-tooltip="<?php esc_html_e("Shows the total money earned by recommended products.", "robera-recommender") ?>">
                    <div class="step" style="width: 16em">
                        <div class="title"><?php echo empty($data) ? "..." : wc_price($data[$key]["sum_bought_value"]) ?></div>
                        <div class="description"><?php esc_html_e("Bought Value", "robera-recommender") ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <h1 class="ui header" style="font-family: rbr-font-family">
                <?php esc_html_e("Robera Recommender Settings", "robera-recommender") ?>
            </h1>
            <div class="content">
                <div class="ui form">
                    <form action="/?rest_route=/<?php echo RECOMMENDER_PLUGIN_PREFIX ?>/v1/settings" method="post">
                        <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('wp_rest') ?>">
                        <div class="inline field">
                            <div>
                                <label style="font-size: 1.07rem"><?php esc_html_e("To add personalized recommendation block in any page (including homepage), use Robera recommendation block inside Woocommerce blocks in page editor", "robera-recommender") ?></label>
                            </div>
                        </div>
                        <div class="inline field">
                            <div class="ui toggle checkbox">
                                <input type="checkbox" tabindex="0" class="hidden"
                                       name="related" <?php echo get_option(RecommenderPlugin::$RECOMMEND_ON_RELATED_PRODUCTS_OPTION_NAME) ? "checked=checked" : "" ?>
                                >
                                <label><?php esc_html_e("Use recommendations for related products.", "robera-recommender") ?></label>
                            </div>
                        </div>
                        <div class="four wide field">
                            <label><?php esc_html_e("Related products section class name", "robera-recommender") ?></label>
                            <div class="ui input">
                                <input type="text" name="rel_section_class" placeholder="related products"
                                       value="<?php echo get_option(RecommenderPlugin::$RECOMMEND_ON_RELATED_PRODUCTS_SECTION_CLASS_OPTION_NAME) ?>"
                                >
                            </div>
                        </div>
                        <input type="submit" class='positive ui button' style="font-family: rbr-font-family;height: unset"
                               value="<?php echo esc_html_e('Submit', 'robera-recommender') ?>"/>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script type="text/javascript">
</script>

