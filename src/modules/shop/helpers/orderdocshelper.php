<?php
namespace Shop;

use Backend;
use Phpr\Controller;
use Dompdf\Dompdf;

class OrderDocsHelper
{

    /**
     * Returns the path to a PDF document for the given order and template/variant
     * Not implemented natively. Custom module must extend event shop:onGetOrderDocPdfFile
     * @param Order $order
     * @param array $template_info
     * @param string $variant
     * @return string|null string of PDF file output
     */
    public static function getPdfOutput($order, $template_info, $variant)
    {
        try {
            $pdfOutputResults = Backend::$events->fireEvent(
                'shop:onGetOrderDocPdfOutput',
                $order,
                $template_info,
                $variant
            );
            if ($pdfOutputResults) {
                foreach ($pdfOutputResults as $pdfOutput) {
                    if (is_string($pdfOutput) && stripos($pdfOutput, '%PDF') === 0) {
                        return $pdfOutput;
                    }
                }
            }
        } catch (\Exception $e) {
            traceLog($e->getMessage());
        }

        $html = self::getHtmlOutput($order, $template_info, $variant, true);
        if ($html) {
            if (!stristr($html, '<html')) {
                $html = '<html>' . PHP_EOL . $html . PHP_EOL . '</html>';
            }
            $domPdf = new Dompdf();
            $domPdf->loadHtml($html);
            $domPdf->setPaper('A4', 'portrait');
            $domPdf->render();
            return $domPdf->output();
        }
        return null;
    }

    /**
     * Returns HTML output for given order and template variant
     * @param Order $order
     * @param array $template_info
     * @param string $variant
     * @return string|false HTML string or FALSE
     */
    public static function getHtmlOutput($order, $templateInfo, $variant, $includeCss = false)
    {
        $viewData = self::get_view_data($order);
        $templateFile = self::get_template_file($order, $templateInfo, $variant);
        $html = null;

        if (!$templateFile) {
            return false;
        }

        $controller = new Controller();
        self::apply_view_data($controller, $viewData);

        ob_start();
            $controller->renderPartial($templateFile, $viewData);
            $html = ob_get_contents();
        ob_end_clean();

        if ($includeCss && $templateInfo['css']) {
            $css = '';
            $cssFiles = $templateInfo['css'];
            $basePath = PATH_APP.'/modules/shop/invoice_templates/';
            foreach ($cssFiles as $cssFile => $media) {
                $path = $basePath.$templateInfo['template_id'].'/resources/css/'.$cssFile;
                if (file_exists($path)) {
                    $css .= '<style type="text/css" media="'.$media.'">'.PHP_EOL;
                    $css .= file_get_contents($path);
                    $css .= PHP_EOL."</style>";
                }
            }
            $html = $css.PHP_EOL.$html;
        }

        return (string) $html;
    }

    public static function get_default_variant($orders, $template_info)
    {
        $firstVariant = null;
        $orders = is_array($orders) ? $orders : array($orders);
        foreach (self::get_applicable_variants($orders, $template_info) as $variant => $params) {
            $firstVariant = !$firstVariant ? $variant : $firstVariant;
            if (isset($params['default']) && $params['default']) {
                return $variant;
            }
        }
        return $firstVariant;
    }

    /**
     * Gets template HTML file
     * @documentable
     * @deprecated Use {@link OrderDocsHelper::get_template_file()} method instead.
     */
    public static function get_template_variant($order, $template_info, $variant)
    {
        self::get_template_file($order, $template_info, $variant);
    }

    public static function get_template_file($order, $template_info, $variant)
    {
        //do specified variant
        if (!empty($variant)) {
            if (self::can_show_template_variant($order, $template_info, $variant)) {
                $templateDir = self::get_template_dir($template_info['template_id']);
                $template = $templateDir . '/variants/' . strtolower($variant) . '.htm';
                if (file_exists($template)) {
                    return $template;
                }
            }
        }

        //use default variant
        $default_variant = self::get_default_variant($order, $template_info);
        $templateDir = self::get_template_dir($template_info['template_id']);
        $template = $templateDir . '/variants/' . strtolower($default_variant) . '.htm';
        if (file_exists($template)) {
            return $template;
        }



        //fallback
        $template = self::get_template_dir($template_info['template_id']).'/invoice.htm';
        if (file_exists($template)) {
            return $template;
        }

        return false;
    }

    public static function get_applicable_variants($orders, $template_info)
    {
        $orders = is_array($orders) ? $orders : array($orders);
        $variants = array();
        if (isset($template_info['variants'])) {
            foreach ($template_info['variants'] as $variant => $params) {
                foreach ($orders as $order) {
                    if (self::can_show_template_variant($order, $template_info, $variant)) {
                        $variants[$variant] = $params;
                    }
                }
            }
        }
        return $variants;
    }


    public static function getVariants($templateInfo = null)
    {
        if (!$templateInfo) {
            $company_info = CompanyInformation::get();
            $templateInfo = $company_info->get_invoice_template();
        }
        return isset($templateInfo['variants']) ? $templateInfo['variants'] : array();
    }

    public static function variantExists($variantName, $templateInfo = null)
    {
        $variants = self::getVariants($templateInfo);
        foreach ($variants as $variant => $params) {
            if ($variant == $variantName) {
                return true;
            }
        }
        return false;
    }

    public static function get_view_data($order)
    {
        $company_info = CompanyInformation::get();
        $template_info = $company_info->get_invoice_template();

        $has_bundles = false;
        foreach ($order->items as $item) {
            if ($item->bundle_master_order_item_id) {
                $has_bundles = true;
                break;
            }
        }
        $invoice_date = $order->get_invoice_date();
        $view_data    = array(
            'order_id'             => $order->id,
            'order'                => $order,
            'company_info'         => $company_info,
            'template_info'        => $template_info,
            'display_tax_included' => CheckoutData::display_prices_incl_tax($order),
            'has_bundles'          => $has_bundles,
            'invoice_date'         => $invoice_date,
            'display_due_date'     => strlen($company_info->invoice_due_date_interval),
            'due_date'             => $company_info->get_invoice_due_date($invoice_date),
        );

        return $view_data;
    }

    public static function can_show_template_variant($order, $template_info, $variant)
    {
        $show_on_status_code = $template_info['variants'][$variant]['on_status'] ?? false;
        $show_if_has_status_code = $template_info['variants'][$variant]['has_status'] ?? false;

        if ($show_on_status_code) {
            $show_on_status_code = is_array($show_on_status_code) ? $show_on_status_code : array($show_on_status_code);
            if (!in_array($order->status->code, $show_on_status_code)) {
                return false;
            }
        }

        if ($show_if_has_status_code) {
            if (!OrderStatusLog::order_has_status_code($order, $show_if_has_status_code)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Presents the order document in controller UI.
     * @param object $controller
     * @param Order $order
     * @param null|string $variant The name of the document template variant
     * @return false|void  Outputs HTML
     */
    public static function render_doc($controller, Order $order, $variant = null)
    {
        $viewData = self::get_view_data($order);
        $html = self::getHtmlOutput($order, $viewData['template_info'], $variant);
        if (!$html) {
            return false;
        }

        //Event allows HTML output to be modified (eg. output HTML that renders a pdf in iframe).
        $htmlModified = Backend::$events->fire_event(
            [
            'name' => 'shop:onBeforeRenderOrderDoc',
            'type' => 'update_result'],
            $html,
            []
        );
        if ($htmlModified) {
            echo $htmlModified;
        }
        echo $html;
    }

    /**
     * Used when document template is custom.
     * Delegates render to event shop:onRenderCustomOrderDoc
     * @param object $controller
     * @param Order $orders Single or collection
     * @param null|string $variant The name of the document template variant
     * @return void  Outputs HTML
     */
    public static function render_custom_doc($controller, $orders, $template_info, $variant = null)
    {
        $orders = is_array($orders) ? $orders : array($orders);
        //Allow HTML output to be modified (eg. use html to create a pdf and show pdf in iframe).
        Backend::$events->fireEvent('shop:onRenderCustomOrderDoc', $controller, $orders, $template_info, $variant);
    }

    public static function get_template_dir($template_id = null)
    {
        $path = PATH_APP."/modules/shop/invoice_templates";

        if ($template_id) {
            return $path.'/'.$template_id;
        }

        return $path;
    }

    public static function get_partial($partial, $template_id)
    {
        return self::get_template_dir($template_id).'/partials/'.$partial.'.htm';
    }

    public static function get_document_urls($template_info, $order_ids, $variant = null)
    {

        $order_ids = is_array($order_ids) ? $order_ids : array($order_ids);
        $order_id_string = urlencode(implode('|', $order_ids));
        if (isset($template_info['custom_render']) && $template_info['custom_render']) {
            $results = Backend::$events->fire_event(
                'shop:onGetCustomOrderDocUrl',
                $template_info,
                $order_ids,
                $variant
            );


            if ($results) {
                $urls = array();
                foreach ($results as $result) {
                    if (is_array($result)) {
                        foreach ($result as $url) {
                            $urls[] = $url;
                        }
                    } else {
                        $urls[] = $result;
                    }
                }
                return $urls;
            }
        }

        return array(root_url(url('/shop/orders/document/'.$order_id_string.'/'.$variant), true));
    }

    protected static function apply_view_data($controller, $view_data)
    {
        if (is_array($view_data)) {
            foreach ($view_data as $key => $value) {
                $controller->viewData[$key] = $value;
            }
        }
    }
}
