<?php

require_once("html-helpers.php");

class Order
{
    public $app;
    public $data;
    public $id;
    public $sort_index;
    public $is_group_order;

    public $producer;
    public $producer_notes;
    public $is_stock_order;
    public $parameters;

    public $date_str;
    // public $weeks_ago;
    // public $week_class_str;
    public $ordered_term;
    public $info_text;
    public $has_adaptable_weights;
    public $show_volume;

    public $articles;
    public $n_articles;
    public $article_comments;
    public $order_comments;
    public $state;
    public $is_open;
    public $is_received;
    public $is_closed;
    public $show;
    public $date_end;
    public $date_pickup;
    public $has_pickup_date;
    public $pickup_date_index;
    public $datetime;
    public $days_in_past;
    public $days_in_future;
    public $week;



    public function __construct($app, $data)
    {
        $this->app = $app;
        $this->data = $data;

        $this->id = $data["id"];
        $this->producer = $data["name"];
        $this->is_stock_order = $this->producer == "Lager";

        $this->state = $data["state"];
        $this->is_open = $this->state == "open"; // Bestellungen noch möglich
        $this->is_closed = $this->state == "closed"; // abgerechnet
        $this->is_received = $this->state == "received" || $this->is_closed || $this->is_stock_order;

        $this->date_end = $data["ends"];
        $this->date_pickup = $data["pickup"] ?? "";
        $this->has_pickup_date = strlen($this->date_pickup) > 0;
        $this->date_end = $this->app->loc_date($this->date_end); // 2021-12-01T16:30:00.000+01:00 => 01.12.2021
        if ($this->has_pickup_date) {
            $datetime = date_create($this->date_pickup);
            $date_str = "Abholung ";
        } else { // no pickupdate specified
            $datetime = date_create($this->date_end);
            $date_str = "Bestellende ";
        }
        $this->days_in_past = (int) ceil($this->app->days_ago($datetime));
        $this->days_in_future = -$this->days_in_past;
        $this->date_str = $date_str . $this->app->date_and_time_ago($datetime, $this->days_in_past);

        $this->week = (int) floor($this->days_in_past / 7.0) + 1;
        if (!$this->has_pickup_date)
            $this->week -= 1; // pickup date could also be more than one week later - better always specify pickup date!
        if ($this->week < 1)
            $this->week = 1;
        // week=1: from future back until 6 days in past
        // week=2: from one week (7 days) ago until 13 days ago
        // week=3: from two weeks (14 days) ago until 20 days ago ...

        $this->sort_index = $this->is_open ? -999 : $this->days_in_past;

        // parameters from producer notes
        $this->producer_notes = $data["supplier_note"] ?? "";
        $items = explode("@pickup:", $this->producer_notes);
        $this->parameters = count($items) == 2 ? json_decode($items[1], true) : [];
        $this->has_adaptable_weights = $this->parameters["adaptable_weights"] ?? !$this->is_stock_order;
        $this->show_volume = $this->parameters["show_volume"] ?? true;
        $this->info_text = $this->parameters["info_text"] ?? "";
        $this->ordered_term = $this->parameters["ordered"] ?? "bestellt";
        $this->show = $this->parameters["show"] ?? true;

        $this->articles = $this->data["articles"] ?? [];
        $this->n_articles = count($this->articles);

        $this->article_comments = [];
        $this->order_comments = [];

        // uncomment for testing
        // $data["comments"] = [["order_comment" => ["text" => "a long comment about whatever 0680123132123 with phone and email user@foodcoop.at"]], ["order_comment" => ["text" => "another comment"]], ["order_comment"=> ["text" => '@pickup:{"info_text": "some info text about order"}']]];
        foreach ($data["comments"] ?? [] as $comment) {
            foreach (explode("\n", $comment["order_comment"]["text"]) as $comment_line) {
                $items = explode("@pickup:", $comment_line);
                $params = count($items) == 2 ? json_decode($items[1], true) : [];
                if (isset($params['info_text'])) {
                    $this->info_text .= $params['info_text'];
                } elseif ($this->app->show_order_comments) {
                    $order_comment = trim($comment_line);
                    if ($order_comment !== "") {
                        $this->order_comments[] = $order_comment;
                    }
                }

                // --- begin remove
                $items = explode("#", $comment_line);
                // "Artikelname... @ Kommentar ... #1234567" =>  ["Artikelname: Kommentar ... ", "1234567"]
                if (count($items) > 1) {
                    $article_id = $items[1];
                    $this->article_comments[$article_id] = explode("@ ", $items[0])[1];
                    continue;
                }
                // --- end remove

                // neu: ... @123456 Kommentar zum Artikel-1 @1234599 Kommentar zum Artikel-2 ...
                // in einer Zeile eigentlich nur 1 Artikel und ein Kommentar dazu!
                $items = explode("@", $comment_line);
                foreach (array_slice($items, 1) as $item) {
                    $end_of_id = strpos($item, " ");
                    $article_id = substr($item, 0, $end_of_id);
                    $this->article_comments[$article_id] = substr($item, $end_of_id + 1);
                }
            }
        }
        // print "<pre>article_comments: ";
        // print_r($this->article_comments);
        // print "</pre>";
    }

    public function var_name($var)
    {
        return $var . "[" . $this->id . "]";
    }
    public function heading_id()
    {
        return "order-$this->id";
    }
    public function html_hidden_input($varname, $value)
    {
        $varname = $this->var_name($varname);
        print "<input type='hidden' name='$varname' value='$value'>\n";
    }

}
?>