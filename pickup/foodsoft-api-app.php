<?php

require_once("foodsoft-app.php");
require_once("api-client.php");

class FoodsoftApiApp extends FoodsoftApp
{
    public $api = null; // for API connection
    public $api_url = "/pickup";
    public $update_result_sum;

    public function needs_api()
    {
        // can be overridden by subclasses if they don't need an API connection 
        // under certain conditions (e.g. for a specific action)
        return true;
    }
    public function __construct($config, $need_api = true)
    {
        parent::__construct($config);

        if ($this->needs_api()) {
            $this->api = new ApiClient($config);
            $this->foodcoop_name = $this->api->foodcoop_name;
            $this->update_result_sum = $config["update_result_sum"] ?? true;
        }

        $this->html_debug_begin();
        if ($this->debug) {
            if ($config["use_local_foodsoft"] ?? false)
                print "using local foodsoft installation at " . $this->api->foodsoft_url . " with date $this->time_now<br>";
            else
                print "using foodsoft server at " . $this->api->foodsoft_url . "<br>";

            // print "test pickup classes: ";
            // print $this->username . " " . $this->ordergroup . " " . $this->ordergroup_id . "\n";

            // print "group orders:";
            // print_r($pickup->group_orders);
        }
        $this->html_debug_end();
    }


    public function get_foodsoft_user()
    {
        $data = $this->api->getResource("/user");
        // print "<pre>";
        // print_r($data);
        // print "</pre>";

        // [id] => 161
        // [name] => David U.
        // [email] => 
        // [locale] => de
        // [ordergroup_name] => keine Bestellgruppe
        // [ordergroup_id] => -1

        $this->username = $data["user"]["name"] ?? null;
        $this->ordergroup = $data["user"]["ordergroup_name"] ?? null;
        $this->ordergroup_id = $data["user"]["ordergroup_id"] ?? null;
        return $this->username;
    }

    public function has_current_user_ordergroup()
    {
        $this->html_debug_begin();
        $this->debug_var("username before get_foodsoft_user", $this->username);

        if (!$this->username) {
            $this->get_foodsoft_user();
        }

        $this->debug_var("username after get_foodsoft_user", $this->username);
        $this->debug_var("ordergroup id", $this->ordergroup_id);
        $this->html_debug_end();

        return $this->ordergroup_id != -1 && $this->ordergroup_id !== null;
    }

    public function get_foodsoft_credit()
    {
        $data = $this->api->getResource("/user/financial_overview");
        $this->credit = $data["financial_overview"]["available_funds"];
        return $this->credit;
    }

    public function get_foodsoft_users($skip_users_without_ordergroup = true)
    {
        $data = $this->api->getResource($this->api_url . "/users");
        $users = $data["users"];

        // filter users without ordergroup
        if ($skip_users_without_ordergroup) {
            $users = array_filter($users, function ($user) {
                return $user["ordergroup_id"] >= 0;
            });
        }

        // handle users without ordergroup
        foreach ($users as $i => $user) {
            if (!$user["ordergroup_name"]) {
                $users[$i]["ordergroup_name"] = "keine Bestellgruppe";
                // print $user["name"] . ": keine Bestellgruppe<br>";
            }
        }

        // filter inactive users
        $inactive_user = $this->config["inactive_user"] ?? "ZZ";
        $users = array_filter($users, function ($user) use ($inactive_user) {
            return $user["ordergroup_name"] && !str_contains($user["ordergroup_name"], $inactive_user);
        });

        // sort users alphabetically
        array_multisort(
            array_column($users, 'name'),
            SORT_ASC,
            $users
        );


        // print "<pre>";
        // //print_r($users);
        // foreach ($users as $user) {
        //     print trim($user["name"]) . " (" . $user["ordergroup_name"] . ")\n";
        // }
        // print "------------------------------------\n\n</pre>";
        // exit;

        // [id] => 251
        // [name] => Anita B
        // [email] => a.b@gmx.net
        // [locale] => de
        // [ordergroup_name] => Anita B & Co
        // [ordergroup_id] => 217

        $this->users = $users;
    }


    public function create_order($data)
    {
        return new Order($this, $data);
    }

    public function set_orders($order_data)
    {
        if (count($order_data) == 0) {
            // print "group_orders array from api is empty!\n";
        }
        $this->orders = $order_data;
        // print "<pre>";
        // print_r($order_data);
        // print "------------------------------------\n\n</pre>";
        // exit;


        foreach ($this->orders as $i => $order) {
            $order = $this->create_order($order);
            $this->orders[$i]["sort-index"] = $order->sort_index;
        }

        array_multisort(
            array_column($this->orders, 'sort-index'),
            SORT_ASC,
            array_column($this->orders, 'name'),
            SORT_ASC,
            $this->orders
        );

        $this->orders_by_date = [];
        $this->orders_days_in_past = [];
        foreach ($this->orders as $i => $order) {
            $order = $this->create_order($order); //$order = new Order($this, $order);
            $this->orders_by_date[$order->date_str][] = $i;
            $this->orders_days_in_past[$order->date_str] = $order->days_in_past;
            // ["pickup-date-0" => [order-index-0, order-index-1, ...], "pickup-date-1" => ... , ..., "pickup-date-n" => ...]
            // order indices refer to the order's position in the $this->group_orders array (not their id)
        }
        $this->orders_by_date_index = array_flip(array_keys($this->orders_by_date));
        // ["pickup-date-0" => 0, "pickup-date-1" => 1, ..., "pickup-date-n" => n]

        // print_r($this->group_orders_by_date);
        // print_r($this->group_orders_by_date_index);
    }

    public function submit_order_updates($order_id, $updates)
    {
        $updates["update_result_sum"] = $this->update_result_sum;
        return $this->api->updateResource(
            $this->api_url . "/$order_id",
            $updates,
            $this->debug
        );
    }

    public function html_foodsoft_url($url, $text)
    {
        print "<a href='" . $this->api->foodsoft_url . $url . "' target='blank'>$text</a>";
    }

    public function html_form_begin($submit_action, $js_onsubmit = "")
    {
        print '<form method="post" ';
        if ($js_onsubmit) {
            print 'onsubmit="return ' . $js_onsubmit . '();" ';
        }
        print 'action="?' . implode("&", [
            "app=" . $this->app_name,
            "action=" . $submit_action,
            "access_token=" . $this->api->access_token,
        ]);
        print '">';
    }

    public function html_form_end($submit_button_text, $instruction = "")
    {
        if ($instruction) {
            print "<p>$instruction</p>";
        }
        print "<p id='p-save-button'>";
        print '<button type="sumbit" class="save-button"
                onclick="window.onbeforeunload = null;">' . $submit_button_text . "</button>";
        print "</p>";
        print "</form>";
    }
}

?>