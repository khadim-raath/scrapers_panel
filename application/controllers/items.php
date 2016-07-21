<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Items extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Items_model');
        $this->load->model('Categories_model');
    }

    public function thesource_scraper() {
        $command = 'java -jar /var/www/yazzoopa-scraper/scrapping_tools/thesource_scraper.jar';
        $output = shell_exec($command);
        redirect('items');
    }

    public function gianttiger_scraper() {
        $command = 'java -jar /var/www/yazzoopa-scraper/scrapping_tools/gianttiger_scraper.jar';
        $output = shell_exec($command);
        redirect('items');
    }

    public function show($id = NULL) {
        if (is_admin()) {
            // $data['data'] = $this->Items_model->get_all_custom_where($where = false, $select = FALSE);
            $ambiguous_alias_select = "t1.*, t2.title_eng as category_title";
            $from_tbl_1 = "items t1";
            $join_array = array(
                array('table' => 'ilance_categories t2', 'condition' => 't1.category = t2.cid', 'direction' => 'left'),
            );
            $where = array("t1.p_id like " => "%" . $id . "%");
            $data['data'] = $this->Items_model->fetch_join_multiple_limit(NULL, NULL, $ambiguous_alias_select, $from_tbl_1, $join_array, $where, $group_by = false, $order_by = "t1.id DESC");
            $data['total'] = 0;
            if ($data['data'] && !empty($data['data'])) {
                $data['total'] = count($data['data']);
            }
            $data['title'] = 'Show Items';
            $data['title'] = "Show $id Items";
            $data["id"] = $id;
            $this->load->view('items/show', $data);
        } else {
            redirect('admin/login');
        }
    }

    public function index() {
        if (is_admin()) {
            $data['data'] = $this->Items_model->get_all_custom_where($where = false, $select = FALSE, $table = "items");
            $data['title'] = 'Scraped Items';
            $this->load->view('items/index', $data);
        } else {
            redirect('welcome');
        }
    }

    public function export_to_CSV($id = NULL) {

        if (is_admin()) {
            $data['title'] = "Export $id Items To CSV";
            $data['id'] = $id;
            $this->load->view('items/export', $data);
        } else {
            redirect('welcome');
        }
    }

    public function export_items($id = NULL) {
        if (is_admin()) {
            // this is for auction  otherwise 0.00]


            $quantity = $this->input->post('quantity');

            $filtered_auctiontype = $this->input->post('auction_type');

            $buynow_price = $this->input->post('buynow_price');
            $starting_price = $this->input->post('starting_price');
            $reserve_price = $this->input->post('reserve_price');

            $auction_split = $this->input->post('auction_split');
            $starting_auction = $this->input->post('starting_auction');
            $buynow_auction = $this->input->post('buynow_auction');
            $reserve_auction = $this->input->post('reserve_auction');

            $name = "$id exported-items"; //This will be the name of the csv file.
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $name . '.csv');
            $output = fopen('php://output', 'wt');
            /*
             * project_title, description, startprice, buynow_price, reserve_price, buynow_qty, buynow_qty_lot,
             * project_details, filtered_auctiontype, cid, sample, currency, city, state, zipcode, country, attributes
             */
//               fputcsv($output, array('heading1', 'heading2', 'heading... n')); //The column heading row of the csv file

            $items = $this->Items_model->get_all($limit = FALSE, $start = 0, $order_by = "id DESC", "p_id like ", "%$id%", $where_second_column_name = "category !=", $where_second_column_value = "");

            $buynow_qty = $quantity;
            $buynow_qty_lot = 1;
            $project_details = "public";

            $currency = "CAD";
            $city = "Toronto";
            $state = "Ontario";
            $zipcode = "M9V5E6";
            $country = "Canada";

            foreach ($items as $key => $value) {
                $value_price = str_replace(',', '', $value['price']);
                $price = explode("$", $value_price);
                $item_price = $price[1];
                if (isset($price[1])) {
                    $item_price = floatval($price[1]);
                }
                $buynowprice = "";
                $startprice = "";
                $reserveprice = 0.00;

                if ($filtered_auctiontype == "regular") {
                    $startprice = $starting_price;
                    $buynowprice = $item_price + $buynow_price; //ok
                    $reserveprice = ($buynowprice / 100) * $reserve_price;
                    $filteredauctiontype = "regular";
                } else if ($filtered_auctiontype == "fixed") {
                    if ($item_price <= $auction_split) {
                        $startprice = $starting_auction;
                        $buynowprice = $item_price + $buynow_auction; //ok
                        $reserveprice = ($buynowprice / 100) * $reserve_auction;
                        $filteredauctiontype = "regular";
                    } else {
                        $buynowprice = $item_price + $buynow_price;
                        $filteredauctiontype = "fixed";
                    }
                } elseif ($filtered_auctiontype == "classified") {
                    $startprice = $starting_price;
                    $buynowprice = $item_price + $buynow_price;
                    $reserveprice = ($buynowprice / 100) * $reserve_price;
                    $filteredauctiontype = "classified";
                }
                $item = array(
                    $value['title'],
                    $value['description'],
                    $startprice,
                    $buynowprice,
                    $reserveprice,
                    $buynow_qty,
                    $buynow_qty_lot,
                    $project_details,
                    $filteredauctiontype,
                    $value['category'],
                    $value['image_url'],
                    $currency,
                    $city,
                    $state,
                    $zipcode,
                    $country
                );
                fputcsv($output, $item);
            }

            fclose($output);
            $this->session->set_flashdata('message', ERROR_MESSAGE . ": No items availabe");
            redirect('items');
        } else {
            redirect('welcome');
        }
    }

    public function assign_categories() {
        if (is_admin()) {
            $where = array("t1.category_title !=" => "");
            /////////
            $ambiguous_alias_select = "t2.cat_id, t2.title as cat_title,count(t1.id) as total,t1.*";
            $from_tbl_1 = "items t1";
            $join_array = array(
                array('table' => 'categories t2', 'condition' => 't1.category_title = t2.title', 'direction' => 'left'),
            );
            // $where = array("t1.p_id like " => "%" . $id . "%");
            $data['data'] = $this->Items_model->fetch_join_multiple_limit(NULL, NULL, $ambiguous_alias_select, $from_tbl_1, $join_array, $where, $group_by = "t1.category_title", $order_by = "t1.p_id ASC");
            /////////////
//            $data['data'] = $this->Items_model->findByCondition($where, $order_by = "category_title ASC", $group_by = "category_title", $select = '*', $like = null);
//            var_dump($data['data']);die;
            $data['title'] = 'Assign Category IDs';
            $this->load->view('items/assign_categories', $data);
        } else {
            redirect('welcome');
        }
    }

    public function assign_cats() {
        if (is_admin()) {
            $item_cats = $_POST;
            for ($i = 1; $i < (count($item_cats) / 2 + 1); $i++) {
                $title = $this->input->post('title_' . $i);
                $id = $this->input->post('id_' . $i);

                $where = array("category_title" => $title);
                $data = array("category" => $id);
                $this->Items_model->updateByCondition($where, $data);
                /////

                if (!$this->Categories_model->get_single("title", $title)) {
                    $data = array("cat_id" => $id, "title" => $title);
                    $this->Categories_model->save($data);
                } else {
                    $where = array("title" => $title);
                    $data = array("cat_id" => $id);
                    $this->Categories_model->updateByCondition($where, $data);
                }

                /////
            }
            $this->session->set_flashdata('message', "Saved Successfully");
            redirect('items/assign_categories');
        } else {
            redirect('welcome');
        }
    }

}
