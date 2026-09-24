<?php
require_once("foodsoft-api-app.php");
require_once("order-distribute.php");
require_once("article-distribute.php");
require_once("html-helpers.php");

class DistributeApp extends FoodsoftApiApp
{
    public $order_ids;
    public $edit_received;
    public $ajax_timeout = 100;
    public $index;


    public function needs_api()
    {
        return !in_array($this->action, [
            "ajax-write",
            "ajax-read",
        ]);
    }

    public function __construct($config)
    {
        parent::__construct($config);

        if (str_contains($this->action, "ajax")) {
            $this->handle_ajax($this->action);
            exit;
        }

        $this->title = "Einkistln";
        $this->order_ids = $this->post["order_ids"] ?? [];
        $this->edit_received = $this->post["edit_received"] ?? true;

        if (!$this->order_ids) {
            $this->get_foodsoft_orders(null, false);
            // print "<pre>";
            // print_r($this->orders);
            // exit;

            $this->html_header(["../distribute.js"], []);
            $this->html_title();
            $this->html_distribute_preselect();
        } else {
            $this->get_foodsoft_orders($this->order_ids); // must be before any html output
            // print "<pre>";
            // print_r($this->orders);
            // exit;

            $this->load_protocolls();

            $this->html_header([
                "../distribute.js",
                "../input.js",
            ], [
                "onload" => "start_update('$this->username', $this->ajax_timeout)",
            ]);
            $this->html_title();
            print html_tag("p", ["class" => "info"], "Deine Eingaben werden laufend gespeichert. " .
                "Wenn du fertig bist, oder ein Mitglied zum Abholen kommt, kannst du deine Stück- und Gewichtsänderungen " .
                "(auch 'nicht geliefert') in die Foodsoft übertragen.");
            $this->html_distribute_form();
            $this->set_index();
            $this->html_bottom_bar();
        }
        $this->html_footer();
    }

    public function handle_ajax($action)
    {
        if ($action == "ajax-write") {
            $ajax_data = $this->get["ajax-data"]; //json encoded array
            $this->save_protocoll($ajax_data);
        } elseif ($action == "ajax-read") {
            $from_event = $this->get['from_event'] ?? 0;
            if ($from_event == -2) { // load all weeks, including current week
                print implode("\n", $this->load_protocolls(true, true));
            } elseif ($from_event == -1) { // load all weeks, excluding current week
                // print "<pre>load_protocolls: ";
                // $this->debug = true;
                // var_dump($this->load_protocolls(true, false));
                // exit();
                print implode("\n", $this->load_protocolls(true, false));
            } else {
                $n_tries = 0;
                do {
                    $new_events = ($from_event == -1) ?
                        $this->load_protocolls(true) :
                        $this->load_protocoll_json(0, $from_event);
                    sleep(1);
                } while (count($new_events) == 0 && $n_tries++ < $this->ajax_timeout);
                print implode("\n", $new_events);
            }
        } elseif ($action == "ajax-save") { // save changes to foodsoft
            $events = $this->load_protocoll();
            $order_updates = [];
            foreach ($events as $event) {
                // "element_id":"input-received-220120","value":0,"grouporder_ids":[2630174,2631106]}
                // "element_id":"input-received_grouporder-2631106","value":0}
                // "element_id":"input-received_grouporder-2630174","value":0}
                // "element_id":"input-weight_received-220396","value":5750,"grouporder_ids":[2628990,2629779,2630037]}
                // "element_id":"input-weight_received_grouporder-2628990","value":2750}
                // "element_id":"input-weight_received_grouporder-2629779","value":1000}
                // "element_id":"input-weight_received_grouporder-2630037","value":2000}    

                $element_id = $event["element_id"] ?? "";
                $order_id = $event["order_id"] ?? null; // "no-order-id";
                if (!$order_id)
                    continue;
                //print_r($event);
                //print "$element_id: ";
                if (str_contains($element_id, "input") && str_contains($element_id, "grouporder")) {
                    $parts = explode("-", $element_id);
                    $grouporder_id = end($parts);
                    $result =
                        str_contains($element_id, "weight") ?
                        $event["received"] ?? $event["value"] / 1000 : $event["value"];
                    // default unit weight 1000 g for testing of legacy data without received
                    $order_updates[$order_id]["updates"][$grouporder_id]["result"] = $result;
                } elseif (str_contains($element_id, "note-textarea")) {
                    $reference = $event["article_name"] . " (" . $event["username"] . " ";
                    if (str_contains($element_id, "balancing"))
                        $reference .= "für Abrechnung)";
                    elseif (key_exists("grouporder_ids", $event) && count($event["grouporder_ids"]) > 1)
                        $reference .= "an alle)";
                    else
                        $reference .= "an " . ($event["ordergroup"] ?? $element_id) . ")";
                    $order_updates[$order_id]["comment"][$reference] = $reference . ": " . $event["value"];
                }
                if ($event["save"] ?? false) {
                    //$order_updates = [];
                }
            }
            //print count($events) . " ";
            foreach ($order_updates as $order_id => $order_update) {
                if (isset($order_updates[$order_id]["comment"]))
                    $order_updates[$order_id]["comment"] = implode("\n\n", $order_updates[$order_id]["comment"]);
            }
            print_r($order_updates); // for testing only

            foreach ($order_updates as $order_id => $order_update) {
                $result = $this->submit_order_updates($order_id, $order_update);
            }

            if ($order_updates) {
                $ajax_data = $this->get["ajax-data"]; //json encoded array
                $this->save_protocoll($ajax_data);
            }
        }
    }


    public function get_foodsoft_orders($order_ids = null, $stock_orders = true)
    {
        $url = $this->api_url . "/orders" .
            ($order_ids ?
                "?ids=" . implode(
                    ",",
                    array_map('strval', $order_ids)
                )
                : ""
            );
        // print "api-url: $url\n";
        $data = $this->api->getResource($url);
        if ($stock_orders) {
            $orders = $data["orders"];
        } else {
            $orders = array_filter($data["orders"], function ($order) {
                // print_r($order);
                // print $order["name"] != "Lager" ? "kein Lager" : "ist Lager";
                // print "\n";
                return $order["name"] != "Lager";
            });
        }

        $this->set_orders($orders);
        // print "<pre>";
        // print_r($this->orders);
        // print "------------------------------------\n\n</pre>";
    }

    public function create_order($data)
    {
        return new OrderDistribute($this, $data);
    }


    public function html_select_orders()
    {
        print "<h2>Bestellungen auswählen</h2>";
        print "<p class='info'>Bitte wähle aus, welche Bestellung(en) du einkistln möchtest:</p>\n";
        print "<p><b>Abholdatum - Lieferantin - Datum Bestellende</b></p>\n";

        foreach ($this->orders_by_date as $date_str => $order_indices) {
            # $date_index = $this->orders_by_date_index[$date_str];
            $class = $this->orders_days_in_past[$date_str] > 0 ? "past-order" : "";
            print html_tag(
                "h3",
                ["class" => $class],
                $date_str
            );
            foreach ($order_indices as $i) {
                $order = new OrderDistribute($this, $this->orders[$i]);
                # $order->pickup_date_index = $date_index;
                print html_tag(
                    "p",
                    ["class" => $class],
                    html_checkbox(
                        "order_ids[]",
                        $order->id,
                        "checkbox-$order->id",
                        "",
                        true,
                        $order->distribute &&
                        $order->days_in_future >= 0 && $order->days_in_future < 7
                    ) .
                    $order->producer . " vom " . $order->date_end
                );
            }
        }
        print html_button("ältere Bestellungen anzeigen", "show-more", "show_more_orders()");
    }






    public function html_distribute_preselect()
    {
        $this->html_form_begin("");
        $this->html_select_user("EinkistlerIn", false);
        $this->html_select_orders();
        $this->html_form_end("Einkistln starten");
    }


    public function set_index()
    {
        $this->index = [];
        foreach ($this->orders as $order_data) {
            $order = $this->create_order($order_data);
            $this->index["_order-" . $order->id] = " "; // spacer
            $this->index["order-" . $order->id] = "=== $order->producer =====";
            $order->sort_articles();
            foreach ($order->articles as $article_data) {
                $this->index["article-" . $article_data["id"]] = str_repeat("&nbsp;", 2) . $article_data["name"];
            }
        }
    }

    public function html_distribute_form()
    {
        $this->order_toc();
        foreach ($this->orders as $order_data) {
            $order = new OrderDistribute($this, $order_data);
            $order->html_heading();
            foreach ($order->articles as $article_data) {
                $article = $order->create_article($article_data);
                $article->html_name();
                $article->html_ordered();
                $input_id = $article->html_received();
                $article->html_article_notes();
                $article->html_buttons($input_id);
                $article->html_group_orders();
                $article->html_difference();
                $article->html_update_received($input_id);
                $order->html_article_index(5);
            }
        }
    }

    public function order_toc()
    {
        if (count($this->orders) > 1) {
            $index = [];
            foreach ($this->orders as $order_data) {
                $order = new OrderDistribute($this, $order_data);
                $index[$order->heading_id()] = $order->name();
            }
            print html_index($index);
        }
    }


    public function html_bottom_bar()
    {
        print html_tag(
            "div",
            [
                "class" => "searchbar",
                "style" => [
                    "position: fixed;",
                    "bottom: 0;",
                    "width: 100%;",
                    "padding: 16px 8px;",
                    "background-color: #EEE;",
                    "z-index: 999;"
                ]
            ],
            html_tag(
                "div",
                ["style" => "float:left"],
                html_select("index", ["0" => "-- Artikel/Bestellung auswählen --"] + $this->index, ["style" => "width: 350px;"])
                // . " " 
                // . html_button(
                //     html_symbol("pfeil-rechts-weiss.png", "text-bottom") . " Foodsoft",
                //     "button-save",
                //     "save_changes();",
                //     true,
                //     ["class" => "save-button-small"]
                // )
            ) .
            html_tag(
                "div",
                ["style" => "float:right"],
                '<span id="seconds"></span>' .
                '<span id="sync-status"></span>' .
                str_repeat("&nbsp;", 5)
            )
        );
        print html_tag("div", ["style" => "padding-bottom: 100px;"], "<!--  margin-bottom -->");
    }
}
