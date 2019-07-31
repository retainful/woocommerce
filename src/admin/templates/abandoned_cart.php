<table cellspacing="0" cellpadding="0" border="0" class="el-table__header"
       style="width: 600px;padding: 0px 20px;border-left: 1px solid #e5e5e5;border-right: 1px solid #e5e5e5;">
    <thead>
    <tr style="text-align: left;">
        <th style="line-height: 56px;width: 20%;padding: 2% 0px;">
            <span style="white-space: normal;line-height: 24px;padding-left: 0px;font-family: Lato,Helvetica,sans-serif;padding-right: 15px;font-size: 16px;text-align: left;"><?php echo __("Item", RNOC_TEXT_DOMAIN); ?> </span>
        </th>
        <th style="width: 17%;">
            <span style="white-space: normal;line-height: 24px;padding-left: 15px;font-family: Lato,Helvetica,sans-serif;padding-right: 15px;font-size: 16px;"><?php echo __("Qty", RNOC_TEXT_DOMAIN); ?> </span>
        </th>
        <th style="width: 20%;">
            <span style="white-space: normal;line-height: 24px;padding-left: 15px;font-family: Lato,Helvetica,sans-serif;padding-right: 15px;font-size: 16px;"><?php echo __("Price", RNOC_TEXT_DOMAIN); ?> </span>
        </th>
        <th style="width: 23%;">
            <span style="white-space: normal;line-height: 24px;padding-left: 15px;font-family: Lato,Helvetica,sans-serif;padding-right: 15px;font-size: 16px;"><?php echo __("Total", RNOC_TEXT_DOMAIN); ?> </span>
        </th>
    </tr>
    </thead>
    <tbody>
    <?php
    if (!empty($line_items)) {
        foreach ($line_items as $item) {
            ?>
            <tr style="line-height: 25px;padding: 20px 0px 20px;">
                <td>
                    <img height="auto" src="<?php echo $item['image_url']; ?>"
                         width="80">
                    <p>
                        <?php echo $item['name']; ?>
                    </p>
                </td>
                <td>
                    <span style="white-space: normal;line-height: 24px;padding-left: 15px;font-family: Lato,Helvetica,sans-serif;padding-right: 15px;"><?php echo $item['quantity_total']; ?></span>
                </td>
                <td>
                    <span style="white-space: normal;line-height: 24px;padding-left: 15px;font-family: Lato,Helvetica,sans-serif;padding-right: 15px;"><?php echo $item['item_subtotal']; ?></span>
                </td>
                <td>
                    <span style="white-space: normal;line-height: 24px;padding-left: 15px;font-family: Lato,Helvetica,sans-serif;padding-right: 15px;"><?php echo $item['item_total_display']; ?></span>
                </td>
            </tr>
            <?php
        }
    }
    ?>
    </tbody>
</table>
<table cellspacing="0" cellpadding="0" border="0" class="el-table__header"
       style="width: 600px;padding: 0px 20px;border-left: 1px solid #e5e5e5;border-right: 1px solid #e5e5e5;">
    <tbody>
    <tr style="background-color:#fff;">

        <td style="vertical-align:top;padding: 15px 40px;text-align: right;border-top: 1px solid #e5e5e5;width: 80%;border-bottom: 1px solid #e5e5e5;">
            <span style="font-weight: 800;line-height: 24px;padding-left: 15px;font-family: Lato,Helvetica,sans-serif;padding-right: 15px;font-size: 15px;"><?php echo __("Cart Total", RNOC_TEXT_DOMAIN); ?></span>
        </td>
        <td style="vertical-align:top;padding: 15px 0px;text-align: left;border-top: 1px solid #e5e5e5;width: 20%;border-bottom: 1px solid #e5e5e5;">
            <span style="font-weight: 800;line-height: 24px;padding-left: 0px;font-family: Lato,Helvetica,sans-serif;padding-right: 14px;font-size: 15px;"><?php echo  $cart_total; ?></span>
        </td>
    </tr>
    </tbody>
</table>
