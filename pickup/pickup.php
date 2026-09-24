<?php
require_once("foodsoft-api-app.php");
require_once("order-pickup.php");
require_once("article-pickup.php");
require_once("order-submit.php");
require_once("article-submit.php");

class PickupApp extends FoodsoftApiApp
{

    public $login_user;
    public $credit;
    public $show_only_received_orders;
    public $self_distribution;
    public $variable_weight_tag;
    public $locked_weight_tags;
    public $n_pickedup_initially = 0;
    public $articles_not_pickedup = [];
    public $comment_level;
    public $protocoll = [];
    public $table_headers = [
        "date" => "Datum",
        #"username" => "Benutzer*in",
        "login_user" => "Benutzer*in Anmeldung",
        "realname" => "Name",
        "ordergroup" => "Bestellgruppe",
        "producer" => "Produzent*in",
        "order_date" => "Bestelldatum",
        # "id" => "Artikel-ID",
        "article_name" => "Artikelname",
        "unit" => "Einheit",
        "price" => "€/Einheit",
        "price_diff" => "€ Differenz",
        "ordered" => "bestellt",
        "received_initial" => "urspr.",
        "received" => "erhalten",
        "weight_ordered" => "Gramm bestellt",
        "weight_received_initial" => "urspr.",
        "weight_received" => "erhalten",
        "single_weights" => "Einzelgewichte",
        "note" => "Notiz"
    ];
    public $show_order_comments = false;

    public function needs_api()
    {
        return !in_array($this->action, ["protocoll"]);
    }

    public function __construct($config)
    {
        parent::__construct($config);

        $this->show_only_received_orders = $config["show_only_received_orders"] ?? false;
        $this->show_order_comments = $config["show_order_comments"] ?? false;
        $this->self_distribution = $config["self_distribution"] ?? false;

        // print "<pre>pickup::construct config:";
        // print_r($config);
        // print "</pre>";

        $this->variable_weight_tag = $this->config["variable_weight"] ?? "*";

        if ($this->action == "protocoll") {
            $this->title = "Protokoll Abholen";
            $this->html_header();
            $this->html_title();
            $this->load_protocolls(); // load protocoll of the last 5 weeks
            $this->generate_table_from_protocoll($this->get["view"] ?? "chronological");
            $this->html_table($this->table, $this->table_headers);
            $this->html_footer();
            exit();
        }

        $this->title = "Abholen";

        if ($this->action == "") {
            if ($this->has_current_user_ordergroup()) {
                // has_current_user_ordergroup is also true if user has no ordergroup but an ordergroup was selected

                // load data before html output to enable token refresh
                $this->login_user = $this->post["login_user"] ?? $this->username;
                $this->credit = $this->get_foodsoft_credit();
                $this->get_foodsoft_group_orders($this->was_ordergroup_selected ? $this->ordergroup_id : null);
                $this->load_article_distribution();
                $this->load_article_pickup_states($this->self_distribution ? "all" : "current"); // for which ordergroup(s)?

                // html output
                $this->html_header([
                    "../pickup.js",
                    "../input.js",
                ], [
                    "onload" => "init()",
                    "onbeforeunload" => "return before_unload()",
                ]);
                $this->html_title();
                $this->html_pickup_form();
            } else {
                $this->html_header();
                $this->html_title();
                $this->html_form_begin("");
                $this->html_select_user("Abholer*in", true);
                print html_hidden_input("login_user", $this->username);
                $this->html_form_end("Weiter");
            }
        } elseif ($this->action == "submit") {
            $this->login_user = $this->post["login_user"];
            $this->comment_level = $this->config["comment_level"] ?? 1; // 0: save no order comments, 1: only article notes, 2: every changes to received  
            $this->html_header();
            $this->html_title();
            $this->html_submit();
        } else {
            print html_tag(
                "p",
                ["class" => "error"],
                "Unbekannte Aktion: " . $this->action
            );
        }

        $this->html_footer();
    }


    public function get_foodsoft_group_orders($ordergroup_id = null)
    {
        $url = $this->api_url;
        if ($ordergroup_id)
            $url .= "/?ordergroup_id=$ordergroup_id";
        $data = $this->api->getResource($url);
        if ($data == NULL) {
            // $token = $this->api->access_token;
            $host = $this->api->foodsoft_host;
            print html_tag(
                "p",
                ["class" => "warning"],
                "getResource(url: '$url') => NULL<br><br>" .
                ($this->config["use_local_foodsoft"] ?? false ?
                    "lokale Foodsoftinstanz nicht gestartet?" :
                    "Fehler bei der Verbindung zum Foodsoft-Server $host " .
                    "oder pickup-Controller auf $host nicht installiert?")
            );
            exit();
        }
        $this->set_orders($data["group_orders"]);
    }



    public function create_order($data)
    {
        return new OrderPickup($this, $data);
    }


    public function html_pickup_form()
    {
        $this->html_form_begin("submit", "check_form");

        print html_list([
            "Abhaken, was du abgeholt bzw. überprüft hast. "
            //."Wenn nicht anders vermerkt, " .
            //."findest du die bestellten Artikel in deinem Kistl."
            ,

            "Gegenüber Bestellung abweichende Stückzahlen und Gewichte eingeben",

            "Abgehaktes speichern und Abweichungen in die Foodsoft übertragen zur " .
            "Berücksichtigung bei der Abrechnung: ganz unten"
        ], true);

        print "<p>";
        print "Benutzer*in: $this->username<br>";
        print "Bestellgruppe: $this->ordergroup<br>";
        print "<b>Dein Name:</b> ";
        print html_tag(
            "input",
            [
                "type" => "text",
                "name" => "realname",
                "value" => $this->username
            ]
        );
        print html_hidden_input("username", $this->username);
        print html_hidden_input("ordergroup", $this->ordergroup);
        print html_hidden_input("ordergroup_id", $this->ordergroup_id);
        print html_hidden_input("login_user", $this->login_user);
        print "</p>";

        // print "      <p class='info'>Wenn du nicht $this->username bist, gib bitte zumindest deinen Vornamen ein. 
        //      Über die " . $this->foodcoop->html_ahref("home/ordergroup", "Foodsoft (Neue Person einladen)") .
        //     "kannst du einen persönlichen Zugang zu deiner Bestellgruppe anlegen, sodass hier
        //      automatisch dein richtiger Name erscheint.</p>\n";

        print "<p>";
        print $this->date_str("now", True) . "<br>";

        if (!$this->was_ordergroup_selected) {
            print "Verfügbares Guthaben: " .
                $this->local_currency_str($this->credit);
        }
        print "</p>";

        // print "was_ordergroup_selected: " . ($this->was_ordergroup_selected ? "true" : "false") . "<br>";
        foreach ($this->orders_by_date as $date_str => $order_indices) {
            $date_index = $this->orders_by_date_index[$date_str];
            print html_tag(
                "h2",
                ["class" => "date", "id" => "date-$date_index"],
                $date_str
            );
            foreach ($order_indices as $i) {
                $order = $this->create_order($this->orders[$i]);
                $order->pickup_date_index = $date_index;
                $order->html();
            }
        }

        print "<p>";
        $n = $this->n_weeks;
        print html_button("zeige mehr Artikel", 'show-more', "show_articles($n);");
        print html_button("zeige weniger Artikel", 'show-less', 'show_articles(1);', false);
        print "</p>";

        $this->html_form_end(
            "Speichern",
            "Die als abgeholt bzw. erledigt markierten Artikel sowie " .
            "die Änderungen von Stückzahl und Gewicht speichern:"
        );
    }






    // ==== process submitted form =========================================================


    private function submitted_order_article_ids()
    {
        return $this->post["order_article_ids"];
    }


    public function html_submit()
    {
        // process submitted form data: submit updates to foodsoft, display results 
        print '<p class="info">Folgende Änderungen wurden zur Abrechnung gespeichert.
                Änderungen, die vorher schon gespeichert wurden, werden nicht mehr angzeigt.
                Wenn du noch nicht fertig bist, oder du noch etwas ausbessern möchtest, geh nochmal zurück.
                Wenn du fertig bist, kannst du diese App bzw. diese Seite schließen. </p>';
        print "<hr>";

        $this->username = $this->post["username"];
        $this->realname = $this->post["realname"];
        $this->ordergroup = $this->post["ordergroup"];

        //$this->debug = TRUE;
        $this->html_debug_begin();
        $this->debug_var("post", $this->post);
        $this->html_debug_end();

        $html = [];
        $html_unchecked = [];
        foreach ($this->submitted_order_article_ids() as $order_id => $article_ids) {
            $order = new OrderSubmitted($this, $order_id);
            foreach ($article_ids as $article_id) {
                $article = new ArticleSubmitted($order, $article_id);

                $article->update_received_if(
                    $article->has_changed("received", 2) &&
                    !$article->is_different("weight_received", "weight_ordered", 0)
                );

                $article->update_received_weight_if(
                    $article->has_changed("weight_received", 0)
                );

                $html[] = $article->html_changes(); // before add_user_note... to exclude user_note, is added in different way here

                $article->add_user_note_if(
                    $article->has_changed("note") ||
                    $article->has_changelog_items() && $article->note
                );
                $this->add_to_protocoll_if(
                    $article->update || $article->has_changed("note"), // array_filter($article->update) ???
                    $article
                );

                $article->update_status_if($article->checked_has_changed());

                $html_unchecked[] = $article->html_unchecked();

                $order->add_update($article);
                $order->add_changelog_entry($article);
            }
            $order->submit_updates(); // submit updates to foodsoft
        }

        if ($this->article_state_save_method == "in-app") {
            $this->save_data("states", $this->articles_pickedup, "current");
        }

        $html = array_filter($html);
        if (count($html) == 0) {
            $html = [
                "<p class='info'>" .
                "Keine Änderungen von Stück oder Gewicht seit dem letzten Speichern.</p>"
            ];
        }

        $n_articles = $this->count("article_name");
        $unchecked_before = $this->count("checked_initial");
        $unchecked_after = $this->count("checked");
        $delta = sprintf("%+d", $unchecked_after - $unchecked_before);
        $html[] = "<p class='info'>" .
            "Deine abgehakten Artikel ($unchecked_after/$n_articles, Änderung: $delta) " .
            "wurden gespeichert.</p>";

        if ($html_unchecked = array_filter($html_unchecked)) {
            $html[] = "<h3>Nicht abgehakte Artikel:</h3>" .
                "<p class='info'>Nicht abgehakte Artikel werden beim Abrechnen genauso von deinem " .
                "Guthaben abgezogen wie abgehakte. Wenn du dir sicher bist, dass Artikel nicht geliefert wurden, " .
                "gehe nochmal zurück und setze die Zahl für Stück bzw. Gewicht bei 'empfangen:' auf Null " .
                "und hake die Artikel ab, im Sinne von 'ist für mich erledigt'.</p>" .

                html_list($html_unchecked, true);
        }

        print implode("\n", $html);

        $this->save_protocoll();

        // http://localhost/pickup/foodcoopsat/franckkistl/?app=pickup&action=&username=Ina&ordergroup=Ina+und+Lukas&ordergroup_id=223&login_user=admin+&access_token=newz1536YOgk1RrzLOt2riZGy9BeZyYgH5GO8GrJXmU
        $query = http_build_query([
            "app" => $this->app_name,
            "action" => "",
            "username" => $this->username,
            "ordergroup" => $this->ordergroup,
            "ordergroup_id" => $this->ordergroup_id,
            "login_user" => $this->login_user,
            "access_token" => $this->api->access_token,
        ]);
        print '<div style="float:left"><a href="?' . $query . '"><button>Zurück</button></a></div>';
        print '<div style="float:right"><a href="?"><button>Fertig</button></a></div>';
        print '<div style="clear:both"></div>';

    }




    // ==== protocoll =========================================================

    public function protocoll_entry($article = null)
    {
        // if no article is given, return the keys and default values for empty protocoll entries
        return [
            "date" => date("Y-m-d H:i:s"),
            #"username" => $this->username,
            "login_user" => $this->login_user,
            "realname" => $this->realname,
            "ordergroup" => $this->ordergroup,
            "producer" => $article->order->producer ?? "",
            "order_date" => $article->order->date_end ?? "",
            "id" => $article->id ?? "",
            "article_name" => $article->name ?? "",
            "unit" => $article->unit ?? "",
            "price" => $article->price ?? 0.,
            "ordered" => $article->ordered ?? 0,
            "received_initial" => $article ? floatval($article->get("received_initial")) : 0,
            "received" => $article->received ?? 0,
            "weight_ordered" => $article->weight_ordered ?? 0,
            "weight_received_initial" => $article ? floatval($article->get("weight_received_initial")) : 0,
            "weight_received" => $article->weight_received ?? 0,
            "single_weights" => $article->single_weights ?? [],
            "note" => $article ? $article->get("note") : "",
        ];
    }
    public function add_to_protocoll_if($condition, $article)
    {
        if ($condition) {
            // print "<pre>";
            // print $article->name . ": ";
            // print "note changed => " . ($article->has_changed("note") ? "true" : "false") . ", ";
            // print "update => " . ($article->update ? "true" : "false") . ", ";
            // var_dump($article->update);
            // print "</pre>";
            $this->protocoll[] = $this->protocoll_entry($article);
        }
    }

    public function table_change($row_index, $key)
    {
        if ($row_index == 0)
            return true;
        return $this->table[$row_index][$key] != $this->table[$row_index - 1][$key];
    }
    public function set_row_class($row_index, $color_index)
    {
        $this->table[$row_index]["class"] =
            ($this->table[$row_index]["received"] == 0 ? "warning " :
                ($color_index % 2 == 1 ? "shaded " : ""));
    }

    public function generate_table_from_protocoll($view = "chronological")
    {
        parent::generate_table_from_protocoll();

        // print "<pre>";
        foreach ($this->table as $i => $row) {
            $this->table[$i]["price_diff"] = ($row["received"] - $row["ordered"]) * $row["price"];
            if ($row["weight_ordered"] == 0) {
                $this->table[$i]["weight_ordered"] = "";
                $this->table[$i]["weight_received"] = "";
                $this->table[$i]["weight_received_initial"] = "";
            }
        }

        if ($view == "chronological") {
            $this->sort_table("date");
            $i_ordergroup = 0;
            foreach ($this->table as $i => $row) {
                if ($i > 0 && $this->table_change($i, "ordergroup"))
                    $i_ordergroup++;
                // print "$i '" . $row["ordergroup"] . "'  $i_ordergroup\n";
                $this->set_row_class($i, $i_ordergroup);
            }
        } elseif ($view == "orders") {
            $this->sort_table("orders");
            $i_article = 0;
            foreach ($this->table as $i => $row) {
                if ($this->table_change($i, "article_name"))
                    $i_article++;
                if (
                    $this->table_change($i, "order_date") ||
                    $this->table_change($i, "producer")
                ) {                // print "$i '" . $row["ordergroup"] . "'  $i_ordergroup\n";
                    $this->table[$i]["heading"] =
                        $row["order_date"] . " " . $row["producer"];
                    $i_article = 0;
                }
                $this->set_row_class($i, $i_article);
            }
        }
        // print "</pre>";
    }

    public function sort_table($sort_by, $order = "asc")
    {
        // sort the data table by the given column name
        if ($sort_by == "orders") {
            print "<pre>";
            // foreach (["order_date", 'producer', 'article_name', 'ordergroup'] as $p) {
            //     print "$p: " . count(array_column($this->table, $p)) . "\n";
            // }
            array_multisort(
                array_column($this->table, 'order_date'),
                SORT_DESC,
                array_column($this->table, 'producer'),
                SORT_ASC,
                array_column($this->table, 'article_name'),
                SORT_ASC,
                array_column($this->table, 'ordergroup'),
                SORT_ASC,
                $this->table
            );
            print "</pre>";
        } else {
            parent::sort_table($sort_by, $order);
        }
    }

}
?>
