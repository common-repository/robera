<?php
namespace Recommender;
$columns = [
    "interaction_type" => esc_html__("interaction type", "robera-recommender"),
    "interaction_time" => esc_html__("interaction time", "robera-recommender"),
    "interaction_value" => esc_html__("interaction value", "robera-recommender"),
    "item_id" => esc_html__("item name (or page title)", "robera-recommender"),
    "price" => esc_html__("price", "robera-recommender"),
];
//$link = isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:"admin.php?page=recommender_users&tab=".$tab;
?>
<div class="semantic" style="font-family: rbr-font-family; !important;">
    <button class="ui labeled icon button" style="font-family: rbr-font-family; height: unset;" onclick="history.back()">
        <i class="<?php echo (is_rtl())?'right':'left' ?> chevron icon"></i>
        <?php esc_html_e('Back', 'robera-recommender'); ?>
    </button>
    <div class="ui horizontal segments">
        <div class="ui segment teal" style="width: 20%">
            <i class="user icon big"></i>
            <div class="title"><?php esc_html_e('User', 'robera-recommender'); ?></div>
            <div class="description"><?php echo $data['user_name'] ?></div>
        </div>
        <div class="ui segment teal" style="width: 20%">
            <i class="large truck icon"></i>
            <div class="title"><?php esc_html_e('Number of purchases', 'robera-recommender'); ?></div>
            <div class="description"><?php echo $data['num_bought'] ?></div>
        </div>
        <div class="ui segment teal" style="width: 20%">
            <i class="large shopping cart icon"></i>
            <div class="title"><?php esc_html_e('Total value of purchases', 'robera-recommender'); ?></div>
            <div class="description"><?php echo wc_price($data['sum_bought_value']) ?></div>
        </div>
        <div class="ui segment teal" style="width: 20%">
            <i class="large shopping cart icon"></i>
            <div class="title"><?php esc_html_e('Register date', 'robera-recommender'); ?></div>
            <div class="description"><?php echo $data['created_at'] ?></div>
        </div>
        <div class="ui segment teal" style="width: 20%">
            <i class="hourglass icon"></i>
            <div class="title"><?php esc_html_e('First visit', 'robera-recommender'); ?></div>
            <div class="description"><?php echo $data['first_visit_data'] ?></div>
        </div>
        <div class="ui segment teal" style="width: 20%">
            <i class="hourglass icon outline"></i>
            <div class="title"><?php esc_html_e('Num visited pages', 'robera-recommender'); ?></div>
            <div class="description"><?php echo $data['num_visited_pages'] ?></div>
        </div>
    </div>
</div>
<div>
    <table class="ui celled padded selectable table green form-table structured">
        <thead>
        <?php foreach ($columns as $key => $value): ?>
            <th style="font-family: rbr-font-family"><?php echo $value ?></th>
        <?php endforeach; ?>
        </thead>
        <tbody>
        <?php foreach ($data['interactions'] as $interaction): ?>
            <?php if ($interaction['interaction_type'] == 'purchase'): ?>
                <?php $c = count($interaction['items']) ?>
                <tr class="positive">
                    <td rowspan="<?php echo $c ?>"><?php echo $interaction_translation['purchase'] ?></td>
                    <td rowspan="<?php echo $c ?>"><?php echo $interaction['interaction_time'] ?></td>
                    <td><?php echo $interaction['items'][0]['interaction_value'].' '.esc_html__('units', 'robera-recommender'); ?></td>
                    <?php $title = wc_get_product($interaction['items'][0]['item_id'])->get_title(); ?>
                    <td><?php echo $title ?></td>
                    <td><?php echo wc_price($interaction['items'][0]['price']) ?></td>
                </tr>
                <?php for ($i = 1; $i < $c; $i++): ?>
                    <tr class="positive">
                        <td><?php echo $interaction['items'][$i]['interaction_value'].' '.esc_html__('units', 'robera-recommender') ?></td>
                        <?php $title = wc_get_product($interaction['items'][$i]['item_id'])->get_title(); ?>
                        <td><?php echo $title ?></td>
                        <td><?php echo wc_price($interaction['items'][$i]['price']) ?></td>
                    </tr>
                <?php endfor; ?>
            <?php else: ?>
                <tr class="<?php if($interaction['interaction_type']=='add_to_cart'): echo "warning"; endif; ?>">
                    <td><?php echo $interaction_translation[$interaction['interaction_type']] ?></td>
                    <td class="<?php echo (isset($interaction['interaction_time'])) ? "" : 'disabled' ?>"><?php echo (isset($interaction['interaction_time'])) ? $interaction['interaction_time'] : '-' ?></td>
                    <td class="<?php echo (isset($interaction['interaction_value'])  && in_array($interaction['interaction_type'], ['view', 'add_to_cart', 'purchase', 'remove_from_cart'])) ? "" : 'disabled' ?>"><?php echo (isset($interaction['interaction_value']) && in_array($interaction['interaction_type'], ['view', 'add_to_cart', 'purchase', 'remove_from_cart'])) ? $interaction['interaction_value'].' '.($interaction['interaction_type']=='view'?esc_html__('seconds', 'robera-recommender'):esc_html__('units', 'robera-recommender')) : '-' ?></td>
                    <?php
                        if (isset($interaction['item_id'])) {
                            $item = wc_get_product($interaction['item_id'])->get_title();
                        }elseif(isset($interaction['page_title'])){
                            $item = $interaction['page_title'];
                        }else{
                            $item = '-';
                        }
                    ?>
                    <td class="<?php echo (isset($interaction['item_id']) || isset($interaction['page_title'])) ? "" : 'disabled' ?>"><?php echo $item ?></td>
                    <td class="disabled"><?php echo '-' ?></td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr>
            <th colspan="9">
                <div class="ui right floated pagination menu" style="padding: unset">
                    <?php
                        $params = ['user_id'=>$user_id, 'user_page'=>max(1, $current_user_page - 1)];
                    ?>
                    <a class="icon item <?php if ($current_user_page == 1): echo "disabled"; endif; ?>" <?php if ($current_user_page != 1): echo 'href="' . $parent_url . '&'.http_build_query($params). '"'; endif; ?>>
                        <i class="<?php echo (is_rtl())?"right":"left"; ?> chevron icon"></i>
                    </a>
                    <?php if ($current_user_page > 3): ?>
                        <?php $params['user_page']=1 ?>
                        <a class="item"
                           href="<?php echo $parent_url . '&'.http_build_query($params) ?>">1</a>
                    <?php endif; ?>
                    <?php if ($current_user_page > 4): ?>
                        <a class="item disabled">...</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $current_user_page - 2); $i <= min($current_user_page + 2, $users_page_num); $i++): ?>
                        <?php $params['user_page']=$i ?>
                        <a class="item <?php if ($i == $current_user_page): echo "active"; endif; ?>"
                           href="<?php echo $parent_url . '&'.http_build_query($params); ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($current_user_page + 3 < $users_page_num): ?>
                        <a class="item disabled">...</a>
                    <?php endif; ?>
                    <?php if ($current_user_page + 2 < $users_page_num): ?>
                        <?php $params['user_page']=$users_page_num ?>
                        <a class="item"
                           href="<?php echo $parent_url . '&'.http_build_query($params); ?>"><?php echo $users_page_num; ?></a>
                    <?php endif; ?>
                    <?php $params['user_page']= min($users_page_num, $current_user_page + 1) ?>
                    <a class="icon item <?php if ($current_user_page == $users_page_num): echo "disabled"; endif; ?>" <?php if ($current_user_page != $users_page_num): echo 'href="' . $parent_url . '&'.http_build_query($params). '"'; endif; ?>>
                        <i class="<?php echo (is_rtl())?"left":"right"; ?> chevron icon"></i>
                    </a>
                </div>
            </th>
        </tr>
        </tfoot>
    </table>
</div>