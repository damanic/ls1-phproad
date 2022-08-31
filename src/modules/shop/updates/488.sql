set @order:=0;
update shop_custom_attributes set sort_order=@order:=@order+1 order by name;

set @order:=0;
update shop_option_set_options set sort_order=@order:=@order+1 order by name;

set @order:=0;
update shop_product_properties set sort_order=@order:=@order+1 order by id;

set @order:=0;
update shop_property_set_properties set sort_order=@order:=@order+1 order by id;