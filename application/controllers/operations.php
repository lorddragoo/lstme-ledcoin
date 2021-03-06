<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of operations
 *
 * @author Andrej
 */
class Operations extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->library('datamapper');
        $this->load->library('session');
        
        auth_redirect_if_not_admin('errormessage/no_admin');
    }
    
    public function index() {
        $this->load->helper('filter');
        
        $operations_addition = new Operation();
        $operations_addition->where('type', Operation::TYPE_ADDITION);
        $operations_addition->select_sum('amount', 'amount_sum');
        $operations_addition->where_related_person('id', '${parent}.id');

        $operations_mined = new Operation();
        $operations_mined->where('type', Operation::TYPE_ADDITION);
        $operations_mined->where('addition_type', Operation::ADDITION_TYPE_MINING);
        $operations_mined->select_sum('amount', 'amount_sum');
        $operations_mined->where_related_person('id', '${parent}.id');

        $operations_subtraction_direct = new Operation();
        $operations_subtraction_direct->where('type', Operation::TYPE_SUBTRACTION);
        $operations_subtraction_direct->where('subtraction_type', Operation::SUBTRACTION_TYPE_DIRECT);
        $operations_subtraction_direct->select_sum('amount', 'amount_sum');
        $operations_subtraction_direct->where_related_person('id', '${parent}.id');

        $operations_subtraction_products = new Operation();
        $operations_subtraction_products->where('type', Operation::TYPE_SUBTRACTION);
        $operations_subtraction_products->where('subtraction_type', Operation::SUBTRACTION_TYPE_PRODUCTS);
        $operations_subtraction_products->where_related('product_quantity', 'price >', 0);
        $operations_subtraction_products->group_start(' NOT', 'AND');
        $operations_subtraction_products->where_related('product_quantity', 'product_id', NULL);
        $operations_subtraction_products->group_end();
        unset($operations_subtraction_products->db->ar_select[0]);
        $operations_subtraction_products->select_func('SUM', array('@product_quantities.quantity', '*', '@product_quantities.price', '*', '@product_quantities.multiplier'), 'amount_sum');
        $operations_subtraction_products->where_related_person('id', '${parent}.id');

        $operations_subtraction_services = new Operation();
        $operations_subtraction_services->where('type', Operation::TYPE_SUBTRACTION);
        $operations_subtraction_services->where('subtraction_type', Operation::SUBTRACTION_TYPE_SERVICES);
        $operations_subtraction_services->where_related('service_usage', 'price >', 0);
        $operations_subtraction_services->group_start(' NOT', 'AND');
        $operations_subtraction_services->where_related('service_usage', 'service_id', NULL);
        $operations_subtraction_services->group_end();
        unset($operations_subtraction_services->db->ar_select[0]);
        $operations_subtraction_services->select_func('SUM', array('@service_usages.quantity', '*', '@service_usages.price', '*', '@service_usages.multiplier'), 'amount_sum');
        $operations_subtraction_services->where_related_person('id', '${parent}.id');

        $persons = new Person();
        $persons->where('admin', 0);
        $persons->select('*');
        $persons->select_subquery($operations_mined, 'mined_amount');
        $persons->select_subquery($operations_addition, 'plus_amount');
        $persons->select_subquery($operations_subtraction_direct, 'minus_amount_direct');
        $persons->select_subquery($operations_subtraction_products, 'minus_amount_products');
        $persons->select_subquery($operations_subtraction_services, 'minus_amount_services');
        $persons->include_related('group', 'title');
        $persons->order_by_related('group', 'title', 'asc')->order_by('surname', 'asc')->order_by('name', 'asc');
        $persons->get_iterated();
        
        $this->parser->parse('web/controllers/operations/index.tpl', array(
            'title' => 'Administrácia / LEDCOIN',
            'new_item_url' => site_url('operations/new_operation'),
            'persons' => $persons,
        ));
    }
    
    public function new_operation($type_override = NULL, $person_id_override = NULL) {
        $this->load->helper('filter');
        
        $operation_data = $this->input->post('operation');
        
        if (!is_null($type_override) && ($type_override == Operation::TYPE_ADDITION || $type_override == Operation::TYPE_SUBTRACTION)) {
            $operation_data['type'] = $type_override;
            $_POST['operation']['type'] = $type_override;
        }
        
        if (!is_null($person_id_override)) {
            $person = new Person();
            $person->where('admin', 0);
            $person->get_by_id((int)$person_id_override);
            if ($person->exists()) {
                $_POST['operation']['person_id'] = $person->id;
            }
        }
        
        $this->parser->parse('web/controllers/operations/new_operation.tpl', array(
            'title' => 'Administrácia / LEDCOIN / Nový záznam',
            'back_url' => site_url('operations'),
            'form' => $this->get_form(@$operation_data['type'], @$operation_data['subtraction_type']),
            'subtype' => @$operation_data['subtraction_type'],
            'type' => @$operation_data['type'],
        ));
    }
    
    public function create_operation() {
        $this->load->helper('operations');
        $operation_data_temp = $this->input->post('operation');
        
        $this->db->trans_begin();
        $form = $this->get_form(@$operation_data_temp['type'], @$operation_data_temp['subtraction_type']);
        build_validator_from_form($form);
        if ($this->form_validation->run()) {
            $operation_data = $this->input->post('operation');
            $operation_service_data = $this->input->post('operation_service');
            $operation_product_data = $this->input->post('operation_product');
            
            $operations_addition = new Operation();
            $operations_addition->where('type', Operation::TYPE_ADDITION);
            $operations_addition->select_sum('amount', 'amount_sum');
            $operations_addition->where_related_person('id', '${parent}.id');

            $operations_subtraction_direct = new Operation();
            $operations_subtraction_direct->where('type', Operation::TYPE_SUBTRACTION);
            $operations_subtraction_direct->where('subtraction_type', Operation::SUBTRACTION_TYPE_DIRECT);
            $operations_subtraction_direct->select_sum('amount', 'amount_sum');
            $operations_subtraction_direct->where_related_person('id', '${parent}.id');

            $operations_subtraction_products = new Operation();
            $operations_subtraction_products->where('type', Operation::TYPE_SUBTRACTION);
            $operations_subtraction_products->where('subtraction_type', Operation::SUBTRACTION_TYPE_PRODUCTS);
            $operations_subtraction_products->where_related('product_quantity', 'price >', 0);
            $operations_subtraction_products->group_start(' NOT', 'AND');
            $operations_subtraction_products->where_related('product_quantity', 'product_id', NULL);
            $operations_subtraction_products->group_end();
            unset($operations_subtraction_products->db->ar_select[0]);
            $operations_subtraction_products->select_func('SUM', array('@product_quantities.quantity', '*', '@product_quantities.price', '*', '@product_quantities.multiplier'), 'amount_sum');
            $operations_subtraction_products->where_related_person('id', '${parent}.id');

            $operations_subtraction_services = new Operation();
            $operations_subtraction_services->where('type', Operation::TYPE_SUBTRACTION);
            $operations_subtraction_services->where('subtraction_type', Operation::SUBTRACTION_TYPE_SERVICES);
            $operations_subtraction_services->where_related('service_usage', 'price >', 0);
            $operations_subtraction_services->group_start(' NOT', 'AND');
            $operations_subtraction_services->where_related('service_usage', 'service_id', NULL);
            $operations_subtraction_services->group_end();
            unset($operations_subtraction_services->db->ar_select[0]);
            $operations_subtraction_services->select_func('SUM', array('@service_usages.quantity', '*', '@service_usages.price', '*', '@service_usages.multiplier'), 'amount_sum');
            $operations_subtraction_services->where_related_person('id', '${parent}.id');
            
            $person = new Person();
            $person->where('admin', 0);
            $person->select('*');
            $person->select_subquery($operations_addition, 'plus_amount');
            $person->select_subquery($operations_subtraction_direct, 'minus_amount_direct');
            $person->select_subquery($operations_subtraction_products, 'minus_amount_products');
            $person->select_subquery($operations_subtraction_services, 'minus_amount_services');
            $person->get_by_id((int)$operation_data['person_id']);

            if (!$person->exists()) {
                $this->db->trans_rollback();
                add_error_flash_message('Účastník sa nenašiel.');
                redirect(site_url('operations/new_operation'));
            }
            
            $admin = new Person();
            $admin->where('admin', 1);
            $admin->get_by_id((int)auth_get_id());
            
            if (!$admin->exists()) {
                $this->db->trans_rollback();
                add_error_flash_message('Administrátor sa nenašiel.');
                redirect(site_url('operations/new_operation'));
            }
            
            $workplace = new Workplace();
            if ((int)$operation_data['workplace_id'] > 0) {
                $workplace->get_by_id((int)$operation_data['workplace_id']);
                
                if (!$workplace->exists()) {
                    $this->db->trans_rollback();
                    add_error_flash_message('Zamestnanie sa nenašlo.');
                    redirect(site_url('operations/new_operation'));
                }
            }
            
            if ($operation_data['type'] == Operation::TYPE_ADDITION) {
                $amount_to_add = (double)$operation_data['amount'];
                $remaining = 0;
                if ($operation_data['addition_type'] == Operation::ADDITION_TYPE_TRANSFER && !operations_ledcoin_addition_possible($amount_to_add, $remaining)) {
                    $this->db->trans_rollback();
                    add_error_flash_message('Nedá sa prideliť <strong>' . $amount_to_add . '</strong> ' . get_inflection_ledcoin($amount_to_add) . ', na účte vedúcich zostáva iba <strong>' . $remaining . '</strong> ' . get_inflection_ledcoin($remaining) . '.');
                    redirect('operations');
                    return;
                }
                if ($operation_data['addition_type'] == Operation::ADDITION_TYPE_TRANSFER && !operations_ledcoin_limit_check($amount_to_add)) {
                    add_common_flash_message('Pozor, pridanie ' . $amount_to_add . ' LEDCOIN-ov presahuje denný limit. Pred pridaním bolo už použitých ' . operations_ledcoin_added_in_day() . ' z ' . operations_ledcoin_daily_limit() . ' LEDCOIN-ov!');
                }
                $operation = new Operation();
                $operation->from_array($operation_data, array('comment', 'amount', 'type', 'addition_type'));
                $operation->subtraction_type = Operation::SUBTRACTION_TYPE_DIRECT;
                if ($operation->save(array('person' => $person, 'admin' => $admin, 'workplace' => $workplace)) && $this->db->trans_status()) {
                    $this->db->trans_commit();
                    add_success_flash_message('Účastník <strong>' . $person->name . ' ' . $person->surname . '</strong> dostal <strong>' . $operation->amount . '</strong> ' . get_inflection_ledcoin((double)$operation->amount) . ' úspešne.');
                    redirect(site_url('operations'));
                } else {
                    $this->db->trans_rollback();
                    add_error_flash_message('Účastníkovi <strong>' . $person->name . ' ' . $person->surname . '</strong> sa nepodarilo prideliť <strong>' . $operation->amount . '</strong> ' . get_inflection_ledcoin((double)$operation->amount) . '.');
                    redirect(site_url('operations/new_operation'));
                }
            } else {
                $amount_at_disposal = doubleval($person->plus_amount) - doubleval($person->minus_amount_direct) - doubleval($person->minus_amount_products) - doubleval($person->minus_amount_services);
                $total_amount = 0;
                
                if ($operation_data['subtraction_type'] == Operation::SUBTRACTION_TYPE_DIRECT) {
                    $total_amount += (double)$operation_data['amount'];
                }
                
                $service_data = array();
                if ($operation_data['subtraction_type'] == Operation::SUBTRACTION_TYPE_SERVICES) {
                    $services = new Service();
                    $services->order_by('title', 'asc');
                    $services->get_iterated();

                    foreach ($services as $service) {
                        if (isset($operation_service_data[$service->id])) {
                            if (isset($operation_service_data[$service->id]['quantity']) && (int)$operation_service_data[$service->id]['quantity'] > 0 &&
                                isset($operation_service_data[$service->id]['price']) && (double)$operation_service_data[$service->id]['price'] > 0) {
                                $service_data[$service->id] = $operation_service_data[$service->id];
                                $total_amount += (int)$operation_service_data[$service->id]['quantity'] * (double)$operation_service_data[$service->id]['price'] * (double)$operation_data['multiplier'];
                            }
                        }
                    }
                }
                
                $product_data = array();
                if ($operation_data['subtraction_type'] == Operation::SUBTRACTION_TYPE_PRODUCTS) {
                    $quantity_addition = new Product_quantity();
                    $quantity_addition->select_sum('quantity', 'quantity_sum');
                    $quantity_addition->where('type', Product_quantity::TYPE_ADDITION);
                    $quantity_addition->where_related('product', 'id', '${parent}.id');

                    $quantity_subtraction = new Product_quantity();
                    $quantity_subtraction->select_sum('quantity', 'quantity_sum');
                    $quantity_subtraction->where('type', Product_quantity::TYPE_SUBTRACTION);
                    $quantity_subtraction->where_related('product', 'id', '${parent}.id');

                    $products = new Product();
                    $products->order_by('title', 'asc');
                    $products->select('*');
                    $products->select_subquery($quantity_addition, 'plus_quantity');
                    $products->select_subquery($quantity_subtraction, 'minus_quantity');
                    $products->get_iterated();
                    
                    foreach ($products as $product) {
                        if (isset($operation_product_data[$product->id])) {
                            if (isset($operation_product_data[$product->id]['quantity']) && (int)$operation_product_data[$product->id]['quantity'] > 0 &&
                                isset($operation_product_data[$product->id]['price']) && (double)$operation_product_data[$product->id]['price'] > 0) {
                                $product_data[$product->id] = $operation_product_data[$product->id];
                                $total_amount += (int)$operation_product_data[$product->id]['quantity'] * (double)$operation_product_data[$product->id]['price'] * (double)$operation_data['multiplier'];
                            }
                        }
                    }
                }
                
                if ($total_amount > $amount_at_disposal) {
                    $this->db->trans_rollback();
                    add_error_flash_message('Účastník <strong>' . $person->name . ' ' . $person->surname . '</strong> nemá dostatok LEDCOIN-u. Potrebuje <strong>' . $total_amount . '</strong> ' . get_inflection_ledcoin((double)$total_amount) . ' ale má iba <strong>' . $amount_at_disposal . '</strong> ' . get_inflection_ledcoin((double)$amount_at_disposal) . '.');
                    redirect(site_url('operations/new_operation'));
                }
                
                if ($total_amount == 0) {
                    $this->db->trans_rollback();
                    add_error_flash_message('Celková suma LEDCOIN-u na odobratie je nulová, preto nie je možné pokračovať.');
                    redirect(site_url('operations/new_operation'));
                }
                
                $operation = new Operation();
                $operation->from_array($operation_data, array('comment', 'type', 'subtraction_type'));
                if ($operation_data['subtraction_type'] == Operation::SUBTRACTION_TYPE_DIRECT) {
                    $operation->amount = (double)$operation_data['amount'];
                } else {
                    $operation->amount = 0.0;
                }
                if ($operation->save(array('person' => $person, 'admin' => $admin, 'workplace' => $workplace)) && $this->db->trans_status()) {
                    if (count($service_data) > 0) {
                        foreach ($service_data as $service_id => $service_post) {
                            $service_usage = new Service_usage();
                            $service_usage->from_array($service_post, array('quantity', 'price'));
                            $service_usage->multiplier = (double)$operation_data['multiplier'];
                            $service_usage->service_id = (int)$service_id;
                            if (!$service_usage->save(array('operation' => $operation))) {
                                $service = new Service();
                                $service->get_by_id((int)$service_id);
                                $this->db->trans_rollback();
                                add_error_flash_message('Nepodarilo sa uložiť záznam o odobratí LEDCOIN-u za službu <strong>' . $service->title . '</strong>.');
                                redirect(site_url('operations/new_operation'));
                                die();
                            }
                        }
                    }
                    if (count($product_data) > 0) {
                        foreach ($product_data as $product_id => $product_post) {
                            $product_quantity = new Product_quantity();
                            $product_quantity->type = Product_quantity::TYPE_SUBTRACTION;
                            $product_quantity->from_array($product_post, array('quantity', 'price'));
                            $product_quantity->multiplier = (double)$operation_data['multiplier'];
                            $product_quantity->product_id = (int)$product_id;
                            if (!$product_quantity->save(array('operation' => $operation))) {
                                $product = new Product();
                                $product->get_by_id((int)$product_id);
                                $this->db->trans_rollback();
                                add_error_flash_message('Nepodarilo sa uložiť záznam o odobratí LEDCOIN-u za produkt <strong>' . $product->title . '</strong>.');
                                redirect(site_url('operations/new_operation'));
                                die();
                            }
                        }
                    }
                    $this->db->trans_commit();
                    add_success_flash_message('Účastníkovi <strong>' . $person->name . ' ' . $person->surname . '</strong> sa úspešne podarilo odobrať <strong>' . $total_amount . '</strong> ' . get_inflection_ledcoin((double)$total_amount) . '.');
                    redirect(site_url('operations'));
                } else {
                    $this->db->trans_rollback();
                    add_error_flash_message('Účastníkovi <strong>' . $person->name . ' ' . $person->surname . '</strong> sa nepodarilo odobrať <strong>' . $total_amount . '</strong> ' . get_inflection_ledcoin((double)$total_amount) . '.');
                    redirect(site_url('operations/new_operation'));
                }
            }
        } else {
            $this->db->trans_rollback();
            $this->new_operation();
        }
    }
    
    public function transactions($person_id = NULL, $page_size = 20, $page = 1) {
        if (is_null($person_id)) {
            add_error_flash_message('Osoba sa nenašla.');
            redirect(site_url('operations'));
        }
        
        $person = new Person();
        $person->where('admin', 0);
        $person->get_by_id((int)$person_id);
        
        if (!$person->exists()) {
            add_error_flash_message('Osoba sa nenašla.');
            redirect(site_url('operations'));
        }
        
        $operations = new Operation();
        $operations->where_related_person($person);
        $operations->include_related('admin', array('name', 'surname'));
        $operations->include_related('workplace', 'title');
        $operations->order_by('created', 'desc');
        $operations->get_paged_iterated($page, $page_size);
        
        $this->parser->parse('web/controllers/operations/transactions.tpl', array(
            'person' => $person,
            'operations' => $operations,
            'title' => 'Administrácia / LEDCOIN / Prehľad transakcií / ' . $person->name . ' ' . $person->surname,
            'back_url' => site_url('operations'),
            'form' => $this->get_transaction_pagination_form($operations->paged),
        ));
    }
    
    public function set_transactions_pagination($person_id = NULL) {
        if (is_null($person_id)) {
            redirect(site_url('operations/transactions'));
        }
        
        $pagination_data = $this->input->post('pagination');
        
        if (array_key_exists('page', $pagination_data) && array_key_exists('page_size', $pagination_data) && (int)$pagination_data['page'] > 0 && (int)$pagination_data['page_size'] > 0) {
            redirect(site_url('operations/transactions/' . $person_id . '/' . (int)$pagination_data['page_size'] . '/' . (int)$pagination_data['page']));
        }
        redirect(site_url('operations/transactions/' . $person_id));
    }
    
    public function batch_ledcoin_addition() {
        $this->load->helper('filter');
        $this->parser->parse('web/controllers/operations/batch_ledcoin_addition.tpl', array(
            'title' => 'Administrácia / LEDCOIN / Hromadné pridanie LEDCOIN-u',
            'form' => $this->get_batch_ledcoin_addition_form(),
            'back_url' => site_url('operations'),
        ));
    }
    
    public function do_batch_ledcoin_addition() {
        $this->load->helper('operations');
        $this->db->trans_begin();
        build_validator_from_form($this->get_batch_ledcoin_addition_form());
        if ($this->form_validation->run()) {
            $batch_amount_data = $this->input->post('batch_amount');
            $person_amount_data = $this->input->post('person_amount');
            
            $workplace = new Workplace();
            if ((int)$batch_amount_data['workplace_id'] > 0) {
                $workplace->get_by_id((int)$batch_amount_data['workplace_id']);
                if (!$workplace->exists()) {
                    $this->db->trans_rollback();
                    add_error_flash_message('Zamestnanie sa nenašlo.');
                    redirect(site_url('operations/batch_ledcoin_addition'));
                }
            }

            $persons = new Person();
            $persons->where('admin', 0);
            $persons->get_iterated();

            $total_added = 0;

            foreach ($persons as $person) {
                if (array_key_exists($person->id, $person_amount_data) && (double)$person_amount_data[$person->id] > 0) {
                    $total_added += (double)$person_amount_data[$person->id];
                }
            }

            $remaining = 0;
            if ($total_added > 0 && $batch_amount_data['addition_type'] == Operation::ADDITION_TYPE_TRANSFER && !operations_ledcoin_addition_possible($total_added, $remaining)) {
                $this->db->trans_rollback();
                add_error_flash_message('Nedá sa prideliť <strong>' . $total_added . '</strong> ' . get_inflection_ledcoin($total_added) . ', na účte vedúcich zostáva iba <strong>' . $remaining . '</strong> ' . get_inflection_ledcoin($remaining) . '.');
                redirect('operations');
                return;
            }

            $persons = new Person();
            $persons->where('admin', 0);
            $persons->get_iterated();
            
            $successful_count = 0;
            $error_count = 0;
            $successful_messages = array();
            $error_messages = array();
            $total_added = 0;

            foreach ($persons as $person) {
                if (array_key_exists($person->id, $person_amount_data) && (double)$person_amount_data[$person->id] > 0) {
                    $operation = new Operation();
                    $operation->admin_id = auth_get_id();
                    $operation->amount = (double)$person_amount_data[$person->id];
                    $operation->type = Operation::TYPE_ADDITION;
                    $operation->subtraction_type = Operation::SUBTRACTION_TYPE_DIRECT;
                    $operation->addition_type = $batch_amount_data['addition_type'];
                    $operation->comment = @$batch_amount_data['comment'];
                    if ($operation->save(array('person' => $person, 'workplace' => $workplace))) {
                        $total_added += (double)$operation->amount;
                        $successful_messages[] = 'Účastník <strong>' . $person->name . ' ' . $person->surname . '</strong> dostal <strong>' . (double)$operation->amount . '</strong> ' . get_inflection_ledcoin((double)$operation->amount) . '.';
                        $successful_count++;
                    } else {
                        $error_count++;
                        $error_messages[] = 'Účastníkovi <strong>' . $person->name . ' ' . $person->surname . '</strong> sa nepodarilo prideliť LEDCOIN.';
                    }
                }
            }

            if ($total_added > 0 && $batch_amount_data['addition_type'] == Operation::ADDITION_TYPE_TRANSFER && !operations_ledcoin_limit_check($total_added)) {
                add_common_flash_message('Pozor, celkovým pridaním ' . $total_added . ' LEDCOIN-ov bol prekročený denný limit. Pred pridaním už bolo pridaných ' . operations_ledcoin_added_in_day() . ' z ' . operations_ledcoin_daily_limit() . ' LEDCOIN-ov!');
            }
            
            if ($successful_count == 0 && $error_count == 0) {
                $this->db->trans_rollback();
                add_error_flash_message('Nikomu nebol pridelený LEDCOIN, nakoľko bol odoslaný prázdny formulár.');
                redirect(site_url('operations'));
            } elseif ($successful_count == 0 && $error_count > 0) {
                $this->db->trans_rollback();
                add_error_flash_message('Nepodarilo sa nikomu pridať LEDCOIN:<br /><br />' . implode('<br />', $error_messages));
            } else {
                $this->db->trans_commit();
                if ($successful_count > 0) {
                    add_success_flash_message('LEDCOIN bol pridelený <strong>' . $successful_count . '</strong> ' . get_inflection_by_numbers($successful_count, 'účastníkom', 'účastníkovi', 'účastníkom', 'účastníkom', 'účastníkom', 'účastníkom') . ':<br /><br />' . implode('<br />', $successful_messages));
                }
                if ($error_count > 0) {
                    add_error_flash_message('LEDCOIN sa nepodarilo udeliť <strong>' . $error_count . '</strong> ' . get_inflection_by_numbers($error_count, 'účastníkom', 'účastníkovi', 'účastníkom', 'účastníkom', 'účastníkom', 'účastníkom') . ':<br /><br />' . implode('<br />', $error_messages));
                }
                redirect(site_url('operations'));
            }
        } else {
            $this->db->trans_rollback();
            $this->batch_ledcoin_addition();
        }        
    }
    
    public function get_batch_ledcoin_addition_form() {
        $workplaces = new Workplace();
        $workplaces->order_by('title', 'asc');
        $workplaces->get_iterated();
        
        $workplace_values = array('' => '');
        foreach ($workplaces as $workplace) {
            $workplace_values[$workplace->id] = $workplace->title;
        }
        
        $form = array(
            'fields' => array(
                'comment' => array(
                    'name' => 'batch_amount[comment]',
                    'id' => 'batch_amount-comment',
                    'label' => 'Komentár',
                    'type' => 'text_input',
                    'data' => array(
                        'stay-visible' => 'true',
                    ),
                    'placeholder' => 'Sem pridajte komentár, alebo nechajte prázdne.',
                    'hint' => 'Pozor, globálne nastavenie pre všetkých účastníkov.',
                ),
                'workplace' => array(
                    'name' => 'batch_amount[workplace_id]',
                    'id' => 'batch_amount-workplace_id',
                    'label' => 'Zamestnanie',
                    'type' => 'select',
                    'data' => array(
                        'stay-visible' => 'true',
                    ),
                    'values' => $workplace_values,
                    'hint' => 'Pozor, globálne nastavenie pre všetkých účastníkov.',
                ),
                'addition_type' => array(
                    'name' => 'batch_amount[addition_type]',
                    'type' => 'select',
                    'id' => 'batch_amount-addition_type',
                    'data' => array(
                        'stay-visivle' => 'true',
                    ),
                    'label' => 'Spôsob pridania LEDCOIN-u',
                    'values' => array(
                        '' => '',
                        Operation::ADDITION_TYPE_TRANSFER => 'Prevod z účtu vedúcich',
                        Operation::ADDITION_TYPE_MINING => 'Vydolovanie LEDCOIN-u',
                    ),
                    'validation' => 'required',
                ),
            ),
            'arangement' => array(
                'workplace', 'comment', 'addition_type'
            ),
        );
        
        $persons = new Person();
        $persons->include_related('group', 'title');
        $persons->where('admin', '0');
        $persons->order_by_related('group', 'title', 'asc')->order_by('surname', 'asc')->order_by('name', 'asc');
        $persons->get_iterated();
        
        if ($persons->exists()) {
            $form['fields']['persons_divider'] = array(
                'type' => 'divider',
                'data' => array(
                    'stay-visible' => 'true',
                ),
            );
            $form['arangement'][] = 'persons_divider';
        }
        
        $current_group = NULL;
        
        foreach ($persons as $person) {
            if ($person->group_id !== $current_group) {
                $form['fields']['divider_group_' . $person->group_id] = array(
                    'type' => 'divider',
                    'data' => array(
                        'stay-visible' => 'true',
                    ),
                );
                if (trim($person->group_title) !== '') {
                    $form['fields']['divider_group_' . $person->group_id]['text'] = 'Skupina: "' . $person->group_title . '"';
                }
                $form['arangement'][] = 'divider_group_' . $person->group_id;
                $current_group = $person->group_id;
                $form['fields']['group_' . $current_group . '_slider'] = array(
                    'name' => 'group[' . $current_group . ']',
                    'id' => 'group-' . $current_group,
                    'class' => 'group_common_slider',
                    'data' => array('group_id' => $current_group),
                    'label' => 'Spoločné nastavenie času',
                    'min' => 0,
                    'max' => 25,
                    'step' => 0.1,
                    'default' => 0,
                    'type' => 'slider',
                );
                $form['arangement'][] = 'group_' . $current_group . '_slider';
            }
            $form['fields']['person_' . $person->id] = array(
                'name' => 'person_amount[' . $person->id . ']',
                'id' => 'person_amount-' . $person->id,
                'class' => 'group_' . $current_group,
                'label' => '<span class="person_name_label"><img src="' . get_person_image_min($person->id) . '" alt="" /><span class="person_name">' . $person->name . ' ' . $person->surname . '</span></span>',
                'type' => 'slider',
                'min' => 0,
                'max' => 25,
                'step' => 0.1,
                'data' => array(
                    'person-name' => $person->name . ' ' . $person->surname,
                    'person-login' => $person->login,
                ),
                'default' => 0,
                'validation' => array(
                    array(
                        'if-field-not-equals' => array('field' => 'person_amount[' . $person->id . ']', 'value' => 0),
                        'rules' => 'required|floatpoint|convert_floatpoint|greater_than[0]',
                    ),
                ),
            );
            $form['arangement'][] = 'person_' . $person->id;
        }
        
        return $form;
    }

    public function get_form($type = '', $subtraction_type = '') {
        $this->load->helper('operations');
        $operations_addition = new Operation();
        $operations_addition->where('type', Operation::TYPE_ADDITION);
        $operations_addition->select_sum('amount', 'amount_sum');
        $operations_addition->where_related_person('id', '${parent}.id');
        
        $operations_subtraction_direct = new Operation();
        $operations_subtraction_direct->where('type', Operation::TYPE_SUBTRACTION);
        $operations_subtraction_direct->where('subtraction_type', Operation::SUBTRACTION_TYPE_DIRECT);
        $operations_subtraction_direct->select_sum('amount', 'amount_sum');
        $operations_subtraction_direct->where_related_person('id', '${parent}.id');
        
        $operations_subtraction_products = new Operation();
        $operations_subtraction_products->where('type', Operation::TYPE_SUBTRACTION);
        $operations_subtraction_products->where('subtraction_type', Operation::SUBTRACTION_TYPE_PRODUCTS);
        $operations_subtraction_products->where_related('product_quantity', 'price >', 0);
        $operations_subtraction_products->group_start(' NOT', 'AND');
        $operations_subtraction_products->where_related('product_quantity', 'product_id', NULL);
        $operations_subtraction_products->group_end();
        unset($operations_subtraction_products->db->ar_select[0]);
        $operations_subtraction_products->select_func('SUM', array('@product_quantities.quantity', '*', '@product_quantities.price', '*', '@product_quantities.multiplier'), 'amount_sum');
        $operations_subtraction_products->where_related_person('id', '${parent}.id');
        
        $operations_subtraction_services = new Operation();
        $operations_subtraction_services->where('type', Operation::TYPE_SUBTRACTION);
        $operations_subtraction_services->where('subtraction_type', Operation::SUBTRACTION_TYPE_SERVICES);
        $operations_subtraction_services->where_related('service_usage', 'price >', 0);
        $operations_subtraction_services->group_start(' NOT', 'AND');
        $operations_subtraction_services->where_related('service_usage', 'service_id', NULL);
        $operations_subtraction_services->group_end();
        unset($operations_subtraction_services->db->ar_select[0]);
        $operations_subtraction_services->select_func('SUM', array('@service_usages.quantity', '*', '@service_usages.price', '*', '@service_usages.multiplier'), 'amount_sum');
        $operations_subtraction_services->where_related_person('id', '${parent}.id');
        
        $persons = new Person();
        $persons->order_by('surname', 'asc')->order_by('name', 'asc');
        $persons->where('admin', 0);
        $persons->select('*');
        $persons->select_subquery($operations_addition, 'plus_amount');
        $persons->select_subquery($operations_subtraction_direct, 'minus_amount_direct');
        $persons->select_subquery($operations_subtraction_products, 'minus_amount_products');
        $persons->select_subquery($operations_subtraction_services, 'minus_amount_services');
        $persons->include_related('group', 'title');
        $persons->get_iterated();
        
        $persons_select = array('' => '');
        
        foreach ($persons as $person) {
            $amount = (doubleval($person->plus_amount) - intval($person->minus_amount_direct) - intval($person->minus_amount_products) - intval($person->minus_amount_services));
            $persons_select[$person->id] = $person->name . ' ' . $person->surname . ' (' . $person->group_title . ' | LEDCOIN: ' . $amount . ' ' . get_inflection_ledcoin($amount) . ')';
        }
        
        $workplaces = new Workplace();
        $workplaces->order_by('title', 'asc');
        $workplaces->get_iterated();
        
        $workplaces_select = array('' => '');
        
        foreach ($workplaces as $workplace) {
            $workplaces_select[$workplace->id] = $workplace->title;
        }
        
        $form = array(
            'fields' => array(
                'type' => array(
                    'name' => 'operation[type]',
                    'type' => 'select',
                    'id' => 'operation-type',
                    'label' => 'Typ operácie',
                    'data' => array(
                        'stay-visible' => 'true',
                    ),
                    'values' => array(
                        '' => '',
                        Operation::TYPE_ADDITION => 'Pridanie LEDCOIN-u',
                        Operation::TYPE_SUBTRACTION => 'Odobratie LEDCOIN-u',
                    ),
                    'validation' => 'required',
                ),
                'subtraction_type' => array(
                    'name' => 'operation[subtraction_type]',
                    'type' => 'select',
                    'id' => 'operation-subtraction_type',
                    'label' => 'Spôsob odobratia LEDCOIN-u',
                    'data' => array(
                        'stay-visible' => 'true',
                    ),
                    'values' => array(
                        '' => '',
                        Operation::SUBTRACTION_TYPE_DIRECT => 'Priame odobratie LEDCOIN-u',
                        Operation::SUBTRACTION_TYPE_PRODUCTS => 'Nákup v bufete',
                        Operation::SUBTRACTION_TYPE_SERVICES => 'Využitie služieb',
                    ),
                    'validation' => 'required',
                ),
                'addition_type' => array(
                    'name' => 'operation[addition_type]',
                    'type' => 'select',
                    'id' => 'operation-addition_type',
                    'data' => array(
                        'stay-visivle' => 'true',
                    ),
                    'label' => 'Spôsob pridania LEDCOIN-u',
                    'values' => array(
                        '' => '',
                        Operation::ADDITION_TYPE_TRANSFER => 'Prevod z účtu vedúcich',
                        Operation::ADDITION_TYPE_MINING => 'Vydolovanie LEDCOIN-u',
                    ),
                    'validation' => 'required',
                ),
                'person' => array(
                    'name' => 'operation[person_id]',
                    'type' => 'select',
                    'id' => 'operation-person_id',
                    'label' => 'Účastník',
                    'data' => array(
                        'stay-visible' => 'true',
                    ),
                    'values' => $persons_select,
                    'validation' => 'required',
                ),
                'workplace' => array(
                    'name' => 'operation[workplace_id]',
                    'type' => 'select',
                    'id' => 'operation-workplace_id',
                    'data' => array(
                        'stay-visible' => 'true',
                    ),
                    'label' => 'Zamestnanie',
                    'values' => $workplaces_select,
                ),
                'comment' => array(
                    'name' => 'operation[comment]',
                    'type' => 'text_input',
                    'id' => 'comment-id',
                    'label' => 'Komentár',
                    'data' => array(
                        'stay-visible' => 'true',
                    ),
                    'validation' => 'max_length[255]',
                ),
                'amount' => array(
                    'name' => 'operation[amount]',
                    'type' => 'slider',
                    'id' => 'comment-amount',
                    'label' => 'LEDCOIN',
                    'data' => array(
                        'stay-visible' => 'true',
                    ),
                    'min' => 0,
                    'max' => 25,
                    'step' => 0.1,
                    'default' => 0,
                    'validation' => array(
                        array(
                            'if-field-not-equals' => array('field' => 'operation[amount]', 'value' => 0),
                            'rules' => 'required|floatpoint|convert_floatpoint|greater_than[0]',
                        ),
                    ),
                ),
                'multiplier-fake' => array(
                    'name' => 'operation[multiplier-fake]',
                    'type' => 'text_input',
                    'disabled' => true,
                    'id' => 'operation-multiplier-fake',
                    'default' => operations_ledcoin_multiplier(),
                    'label' => 'Multiplikátor LEDCOIN-u',
                ),
                'multiplier' => array(
                    'name' => 'operation[multiplier]',
                    'type' => 'hidden',
                    'default' => operations_ledcoin_multiplier(),
                ),
            ),
            'arangement' => array(
                'type', 'person', 'workplace', 'comment'
            ),
        );
        
        if ($type == Operation::TYPE_SUBTRACTION) {
            if ($subtraction_type == Operation::SUBTRACTION_TYPE_DIRECT) {
                $form['arangement'] = array('type', 'subtraction_type', 'person', 'workplace', 'comment', 'amount');
            } elseif ($subtraction_type == Operation::SUBTRACTION_TYPE_SERVICES) {
                $form['arangement'] = array('type', 'subtraction_type', 'person', 'comment', 'multiplier', 'multiplier-fake');
                $services = new Service();
                $services->order_by('title', 'asc');
                $services->get_iterated();

                foreach ($services as $service) {
                    $form['fields']['service_' . $service->id . '_quantity'] = array(
                        'name' => 'operation_service[' . $service->id . '][quantity]',
                        'class' => 'controlls-services',
                        'id' => 'operation_service-' . $service->id . '-quantity',
                        'type' => 'slider',
                        'min' => 0,
                        'max' => 240,
                        'label' => $service->title . ' (LEDCOIN)',
                        'data' => array(
                            'service-title' => $service->title,
                        ),
                        'default' => 0,
                        'validation' => array(
                            array(
                                'if-field-not-equals' => array('field' => 'operation_service[' . $service->id . '][quantity]', 'value' => 0),
                                'rules' => 'required|integer|greater_than[0]',
                            ),
                        ),
                    );
                    $form['fields']['service_' . $service->id . '_price'] = array(
                        'name' => 'operation_service[' . $service->id . '][price]',
                        'class' => 'controlls-services',
                        'id' => 'operation_service-' . $service->id . '-price',
                        'type' => 'text_input',
                        'label' => $service->title . ' (cena za minútu)',
                        'data' => array(
                            'service-title' => $service->title,
                        ),
                        'default' => $service->price,
                        'validation' => array(
                            array(
                                'if-field-not-equals' => array('field' => 'operation_service[' . $service->id . '][quantity]', 'value' => 0),
                                'rules' => 'required|floatpoint|convert_floatpoint|greater_than[0]',
                            ),
                        ),
                    );

                    $form['arangement'][] = 'service_' . $service->id . '_quantity';
                    $form['arangement'][] = 'service_' . $service->id . '_price';
                }
            } elseif ($subtraction_type == Operation::SUBTRACTION_TYPE_PRODUCTS) {
                $form['arangement'] = array('type', 'subtraction_type', 'person', 'comment', 'multiplier', 'multiplier-fake');
                
                $quantity_addition = new Product_quantity();
                $quantity_addition->select_sum('quantity', 'quantity_sum');
                $quantity_addition->where('type', Product_quantity::TYPE_ADDITION);
                $quantity_addition->where_related('product', 'id', '${parent}.id');

                $quantity_subtraction = new Product_quantity();
                $quantity_subtraction->select_sum('quantity', 'quantity_sum');
                $quantity_subtraction->where('type', Product_quantity::TYPE_SUBTRACTION);
                $quantity_subtraction->where_related('product', 'id', '${parent}.id');

                $products = new Product();
                $products->order_by('title', 'asc');
                $products->select('*');
                $products->select_subquery($quantity_addition, 'plus_quantity');
                $products->select_subquery($quantity_subtraction, 'minus_quantity');
                $products->get_iterated();

                $p = 1;
                foreach ($products as $product) {
                    $form['fields']['product_' . $product->id . '_quantity'] = array(
                        'name' => 'operation_product[' . $product->id . '][quantity]',
                        'class' => 'controlls-products',
                        'id' => 'operation_product-' . $product->id . '-quantity',
                        'type' => 'slider',
                        'min' => 0,
                        'max' => intval($product->plus_quantity) - intval($product->minus_quantity),
                        'label' => '<span class="product_title_label"><img src="' . get_product_image_min($product->id) . '" alt="" /><span class="product_title">' . $product->title . ' (počet kusov)</span></span>',
                        'default' => 0,
                        'disabled' => intval($product->plus_quantity) - intval($product->minus_quantity) <= 0 ? true : false,
                        'data' => array(
                            'product-title' => $product->title,
                        ),
                        'validation' => array(
                            array(
                                'if-field-not-equals' => array('field' => 'operation_product[' . $product->id . '][quantity]', 'value' => 0),
                                'rules' => 'required|integer|greater_than[0]|less_than_equals[' . (intval($product->plus_quantity) - intval($product->minus_quantity)) . ']',
                            ),
                        ),
                    );
                    $form['fields']['product_' . $product->id . '_price'] = array(
                        'name' => 'operation_product[' . $product->id . '][price]',
                        'class' => 'controlls-products',
                        'id' => 'operation_product-' . $product->id . '-price',
                        'type' => 'text_input',
                        'label' => $product->title . ' (cena za kus)',
                        'default' => $product->price,
                        'disabled' => intval($product->plus_quantity) - intval($product->minus_quantity) <= 0 ? true : false,
                        'data' => array(
                            'product-title' => $product->title,
                        ),
                        'validation' => array(
                            array(
                                'if-field-not-equals' => array('field' => 'operation_product[' . $product->id . '][quantity]', 'value' => 0),
                                'rules' => 'required|floatpoint|convert_floatpoint|greater_than[0]',
                            ),
                        ),
                    );

                    $form['arangement'][] = 'product_' . $product->id . '_quantity';
                    $form['arangement'][] = 'product_' . $product->id . '_price';
                    if ($p < $products->result_count()) {
                        $form['fields']['product_' . $product->id . '_divider'] = array(
                            'type' => 'divider',
                            'data' => array(
                                'product-title' => $product->title,
                            ),
                        );
                        $form['arangement'][] = 'product_' . $product->id . '_divider';
                    }
                    $p++;
                }
            } else {
                $form['arangement'] = array('type', 'subtraction_type', 'person');
            }
        } else {
            $form['arangement'][] = 'addition_type';
            $form['arangement'][] = 'amount';
        }
        
        if ($type == Operation::TYPE_ADDITION) {
            $form['fields']['amount']['validation'] = 'required|floatpoint|convert_floatpoint|greater_than[0]';
        } elseif ($type == Operation::TYPE_SUBTRACTION) {
            
        } else {
            $form['arangement'] = array('type');
        }
        
        return $form;
    }
    
    protected function get_transaction_pagination_form($pagination) {
        $pages = array();
        for ($i = 1; $i <= $pagination->total_pages; $i++) {
            $pages[$i] = $i . '. stránka';
        }
        $form = array(
            'fields' => array(
                'page' => array(
                    'name' => 'pagination[page]',
                    'type' => 'select',
                    'id' => 'pagination-page',
                    'label' => 'Stránka',
                    'values' => $pages,
                    'default' => $pagination->current_page,
                    'object_property' => 'current_page',
                ),
                'page_size' => array(
                    'name' => 'pagination[page_size]',
                    'type' => 'select',
                    'id' => 'pagination-page_size',
                    'label' => 'Veľkosť stránky',
                    'values' => array(
                        10 => '10 záznamov',
                        20 => '20 záznamov',
                        30 => '30 záznamov',
                        40 => '40 záznamov',
                        50 => '50 záznamov',
                    ),
                    'default' => $pagination->page_size,
                    'object_property' => 'page_size',
                ),
            ),
            'arangement' => array(
                'page', 'page_size',
            ),
        );
        return $form;
    }
}

