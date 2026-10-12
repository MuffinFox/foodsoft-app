<?php
require_once "order.php";
class OrderPickup extends Order
{
    public $grouporder_id;
    public $ready_for_pickup;
    public function __construct($app, $data)
    {
        parent::__construct($app, $data);

        // print "<pre>";
        // print_r($data);
        // print "</pre>";

        $this->grouporder_id = $this->id;
        $this->id = $data["order_id"];
        $this->ready_for_pickup =
            $this->has_pickup_date && $this->days_in_past >= 0 ||
            $this->is_open || $this->is_stock_order;

    }

    public function create_article($article_data)
    {
        return new ArticlePickup($this, $article_data);
    }

    function html()
    {
        $order_class = "order-" . $this->id;

        $comment_popover_html = "";
        $has_comments = $this->app->show_order_comments && ([] !== $this->order_comments);
        if ($has_comments) {
            $comment_popover_html =
                "<button type='button' class='info-icon' " .
                "onclick='event.stopPropagation(); toggle_comment_popover(\"$this->id\")' " .
                "title='Kommentare zur Bestellung' aria-label='Kommentare zur Bestellung'>" .
                info_icon() .
                "</button>" .
                "<div class='comment-popover' id='comment-popover-$this->id'>" .
                html_list(array_map('linkify_contacts', $this->order_comments)) .
                "</div>";
        }

        $order_classes = "order $order_class" . ($has_comments ? " has-info-icon" : "");
        $summary_url = "?" . http_build_query([
            "app" => "pickup",
            "action" => "summary",
            "order_id" => $this->id,
            "access_token" => $this->app->api->access_token,
        ]);

        // show link to pickup summary if order already received and feature enabled 
        $summary_link = '';
        if ($this->app->show_summary_link && $this->is_received) {
            $summary_link = html_tag("a", [
                "class" => "summary-link",
                "href" => $summary_url,
                "target" => "_blank",
                "rel" => "noopener",
                "title" => "Abholungsübersicht dieser Bestellung in neuem Fenster öffnen",
            ], "Übersicht");
        }
        print "<h3 class='$order_classes'>$comment_popover_html" . $this->producer . " $summary_link</h3>";
        // print "<p>Status: $this->state, " .
        //     ($this->is_received ? "received" : "not received") . "," .
        //     ($this->app->show_only_received_orders ? "show only received" : "show all") .
        //     "</p>";

        if ($this->info_text) {
            print "<p class='info $order_class'>$this->info_text</p>";
        }

        if ($this->is_open) {
            print "<p class='info'>Bestellung ist noch offen - Abweichungen bitte in der ";
            $this->app->html_foodsoft_url( // "/group_orders/37402/edit?order_id=6228"
                "/group_orders/" . $this->grouporder_id .
                "/edit?order_id=" . $this->id,
                "Foodsoft-Bestellung"
            );
            print " eingeben!</p>";

        }

        if (
            $this->app->show_only_received_orders && !$this->is_received ||
            !$this->show
        ) {
            print html_tag(
                "p",
                ["class" => ["info", $order_class]],
                ($this->show ? "Bestellung" : "Lieferantin") .
                " ist noch nicht freigegeben: " .
                "Bestellt wurde: " .
                implode(", ", $this->article_names())
            );
            return;
        }

        $this->html_hidden_input("producer", $this->producer);
        $this->html_hidden_input("date", $this->date_pickup);
        $this->html_hidden_input("is_closed", $this->is_closed);

        foreach ($this->articles as $article_data) {
            $this->create_article($article_data)->html_form();
        }
    }

    public function article_names()
    {
        $names = [];
        foreach ($this->articles as $article_data) {
            $names[] = $this->create_article($article_data)->name;
        }
        return $names;
    }



}