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
        $this->load->library('session');
        
        auth_redirect_if_not_admin('error/no_admin');
    }
    
    public function index() {
        $operations_addition = new Operation();
        $operations_addition->where('type', 'addition');
        $operations_addition->select_sum('time', 'time_sum');
        $operations_addition->where_related_person('id', '${parent}.id');

        $operations_subtraction_simple = new Operation();
        $operations_subtraction_simple->where('type', 'subtraction');
        $operations_subtraction_simple->select_sum('time', 'time_sum');
        $operations_subtraction_simple->where_related_person('id', '${parent}.id');

        $operations_subtraction_advanced_1 = new Operation();
        $operations_subtraction_advanced_1->where('type', 'subtraction');
        $operations_subtraction_advanced_1->where_related('product_quantity', 'price >', 0);
        $operations_subtraction_advanced_1->group_start(' NOT', 'AND');
        $operations_subtraction_advanced_1->where_related('product_quantity', 'product_id', NULL);
        $operations_subtraction_advanced_1->group_end();
        unset($operations_subtraction_advanced_1->db->ar_select[0]);
        $operations_subtraction_advanced_1->select_func('SUM', array('@product_quantities.quantity', '*', '@product_quantities.price'), 'time_sum');
        $operations_subtraction_advanced_1->where_related_person('id', '${parent}.id');

        $operations_subtraction_advanced_2 = new Operation();
        $operations_subtraction_advanced_2->where('type', 'subtraction');
        $operations_subtraction_advanced_2->where_related('service_usage', 'price >', 0);
        $operations_subtraction_advanced_2->group_start(' NOT', 'AND');
        $operations_subtraction_advanced_2->where_related('service_usage', 'service_id', NULL);
        $operations_subtraction_advanced_2->group_end();
        unset($operations_subtraction_advanced_2->db->ar_select[0]);
        $operations_subtraction_advanced_2->select_func('SUM', array('@service_usages.quantity', '*', '@service_usages.price'), 'time_sum');
        $operations_subtraction_advanced_2->where_related_person('id', '${parent}.id');

        $persons = new Person();
        $persons->where('admin', 0);
        $persons->select('*');
        $persons->select_subquery($operations_addition, 'plus_time');
        $persons->select_subquery($operations_subtraction_simple, 'minus_time_1');
        $persons->select_subquery($operations_subtraction_advanced_1, 'minus_time_2');
        $persons->select_subquery($operations_subtraction_advanced_2, 'minus_time_3');
        $persons->include_related('group', 'title');
        $persons->get_iterated();
        
        $this->parser->parse('web/controllers/operations/index.tpl', array(
            'title' => 'Administrácia / Strojový čas',
            'new_item_url' => site_url('operations/new_operation'),
            'persons' => $persons,
        ));
    }
    
    public function new_operation($type_override = NULL, $person_id_override = NULL) {
        $operation_data = $this->input->post('operation');
        
        if (!is_null($type_override) && ($type_override == 'addition' || $type_override == 'subtraction')) {
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
            'title' => 'Administrácia / Strojový čas / Nový záznam',
            'back_url' => site_url('operations'),
            'form' => $this->get_form(@$operation_data['type']),
        ));
    }
    
    public function create_operation() {
        $operation_data_temp = $this->input->post('operation');
        
        $this->db->trans_begin();
        $form = $this->get_form(@$operation_data_temp['type']);
        build_validator_from_form($form);
        if ($this->form_validation->run()) {
            $operation_data = $this->input->post('operation');
            $operation_service_data = $this->input->post('operation_service');
            $operation_product_data = $this->input->post('operation_product');
            
            $operations_addition = new Operation();
            $operations_addition->where('type', 'addition');
            $operations_addition->select_sum('time', 'time_sum');
            $operations_addition->where_related_person('id', '${parent}.id');

            $operations_subtraction_simple = new Operation();
            $operations_subtraction_simple->where('type', 'subtraction');
            $operations_subtraction_simple->select_sum('time', 'time_sum');
            $operations_subtraction_simple->where_related_person('id', '${parent}.id');

            $operations_subtraction_advanced_1 = new Operation();
            $operations_subtraction_advanced_1->where('type', 'subtraction');
            $operations_subtraction_advanced_1->where_related('product_quantity', 'price >', 0);
            $operations_subtraction_advanced_1->group_start(' NOT', 'AND');
            $operations_subtraction_advanced_1->where_related('product_quantity', 'product_id', NULL);
            $operations_subtraction_advanced_1->group_end();
            unset($operations_subtraction_advanced_1->db->ar_select[0]);
            $operations_subtraction_advanced_1->select_func('SUM', array('@product_quantities.quantity', '*', '@product_quantities.price'), 'time_sum');
            $operations_subtraction_advanced_1->where_related_person('id', '${parent}.id');

            $operations_subtraction_advanced_2 = new Operation();
            $operations_subtraction_advanced_2->where('type', 'subtraction');
            $operations_subtraction_advanced_2->where_related('service_usage', 'price >', 0);
            $operations_subtraction_advanced_2->group_start(' NOT', 'AND');
            $operations_subtraction_advanced_2->where_related('service_usage', 'service_id', NULL);
            $operations_subtraction_advanced_2->group_end();
            unset($operations_subtraction_advanced_2->db->ar_select[0]);
            $operations_subtraction_advanced_2->select_func('SUM', array('@service_usages.quantity', '*', '@service_usages.price'), 'time_sum');
            $operations_subtraction_advanced_2->where_related_person('id', '${parent}.id');
            
            $person = new Person();
            $person->where('admin', 0);
            $person->select('*');
            $person->select_subquery($operations_addition, 'plus_time');
            $person->select_subquery($operations_subtraction_simple, 'minus_time_1');
            $person->select_subquery($operations_subtraction_advanced_1, 'minus_time_2');
            $person->select_subquery($operations_subtraction_advanced_2, 'minus_time_3');
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
            
            if ($operation_data['type'] == 'addition') {
                $operation = new Operation();
                $operation->from_array($operation_data, array('comment', 'time', 'type'));
                if ($operation->save(array('person' => $person, 'admin' => $admin, 'workplace' => $workplace)) && $this->db->trans_status()) {
                    $this->db->trans_commit();
                    add_success_flash_message('Účastník <strong>' . $person->name . ' ' . $person->surname . '</strong> dostal <strong>' . $operation->time . '</strong> ' . get_inflection_by_numbers((int)$operation->time, 'minút', 'minútu', 'minúty', 'minúty', 'minúty', 'minút') . ' strojového času úspešne.');
                    redirect(site_url('operations'));
                } else {
                    $this->db->trans_rollback();
                    add_error_flash_message('Účastníkovi <strong>' . $person->name . ' ' . $person->surname . '</strong> sa nepodarilo prideliť <strong>' . $operation->time . '</strong> ' . get_inflection_by_numbers((int)$operation->time, 'minút', 'minútu', 'minúty', 'minúty', 'minúty', 'minút') . ' strojového času.');
                    redirect(site_url('operations/new_operation'));
                }
            } else {
                $time_at_disposal = intval($person->plus_time) - intval($person->minus_time_1) - intval($person->minus_time_2) - intval($person->minus_time_3);
                $total_time = (int)$operation_data['time'];
                
                $services = new Service();
                $services->order_by('title', 'asc');
                $services->get_iterated();
                
                $service_data = array();
                
                foreach ($services as $service) {
                    if (isset($operation_service_data[$service->id])) {
                        if (isset($operation_service_data[$service->id]['quantity']) && (int)$operation_service_data[$service->id]['quantity'] > 0 &&
                            isset($operation_service_data[$service->id]['price']) && (int)$operation_service_data[$service->id]['price'] > 0) {
                            $service_data[$service->id] = $operation_service_data[$service->id];
                            $total_time += (int)$operation_service_data[$service->id]['quantity'] * (int)$operation_service_data[$service->id]['price'];
                        }
                    }
                }
                
                $quantity_addition = new Product_quantity();
                $quantity_addition->select_sum('quantity', 'quantity_sum');
                $quantity_addition->where('type', 'addition');
                $quantity_addition->where_related('product', 'id', '${parent}.id');

                $quantity_subtraction = new Product_quantity();
                $quantity_subtraction->select_sum('quantity', 'quantity_sum');
                $quantity_subtraction->where('type', 'subtraction');
                $quantity_subtraction->where_related('product', 'id', '${parent}.id');

                $products = new Product();
                $products->order_by('title', 'asc');
                $products->select('*');
                $products->select_subquery($quantity_addition, 'plus_quantity');
                $products->select_subquery($quantity_subtraction, 'minus_quantity');
                $products->get_iterated();
                
                $product_data = array();
                
                foreach ($products as $product) {
                    if (isset($operation_product_data[$product->id])) {
                        if (isset($operation_product_data[$product->id]['quantity']) && (int)$operation_product_data[$product->id]['quantity'] > 0 &&
                            isset($operation_product_data[$product->id]['price']) && (int)$operation_product_data[$product->id]['price'] > 0) {
                            $product_data[$product->id] = $operation_product_data[$product->id];
                            $total_time += (int)$operation_product_data[$product->id]['quantity'] * (int)$operation_product_data[$product->id]['price'];
                        }
                    }
                }
                
                if ($total_time > $time_at_disposal) {
                    $this->db->trans_rollback();
                    add_error_flash_message('Účastník <strong>' . $person->name . ' ' . $person->surname . '</strong> nemá dostatok strojového času. Potrebuje <strong>' . $total_time . '</strong> ' . get_inflection_by_numbers((int)$total_time, 'minút', 'minútu', 'minúty', 'minútu', 'minúty', 'minút') . ' ale má iba <strong>' . $time_at_disposal . '</strong> ' . get_inflection_by_numbers((int)$time_at_disposal, 'minút', 'minútu', 'minúty', 'minútu', 'minúty', 'minút') . '.');
                    redirect(site_url('operations/new_operation'));
                }
                
                if ($total_time == 0) {
                    $this->db->trans_rollback();
                    add_error_flash_message('Celková suma strojového času na odobratie je nulová, preto nie je možné pokračovať.');
                    redirect(site_url('operations/new_operation'));
                }
                
                $operation = new Operation();
                $operation->from_array($operation_data, array('comment', 'time', 'type'));
                if ($operation->save(array('person' => $person, 'admin' => $admin, 'workplace' => $workplace)) && $this->db->trans_status()) {
                    if (count($service_data) > 0) {
                        foreach ($service_data as $service_id => $service_post) {
                            $service_usage = new Service_usage();
                            $service_usage->from_array($service_post, array('quantity', 'price'));
                            $service_usage->service_id = (int)$service_id;
                            if (!$service_usage->save(array('operation' => $operation))) {
                                $service = new Service();
                                $service->get_by_id((int)$service_id);
                                $this->db->trans_rollback();
                                add_error_flash_message('Nepodarilo sa uložiť záznam o odobratí strojového času za službu <strong>' . $service->title . '</strong>.');
                                redirect(site_url('operations/new_operation'));
                                die();
                            }
                        }
                    }
                    if (count($product_data) > 0) {
                        foreach ($product_data as $product_id => $product_post) {
                            $product_quantity = new Product_quantity();
                            $product_quantity->type = 'subtraction';
                            $product_quantity->from_array($product_post, array('quantity', 'price'));
                            $product_quantity->product_id = (int)$product_id;
                            if (!$product_quantity->save(array('operation' => $operation))) {
                                $product = new Product();
                                $product->get_by_id((int)$product_id);
                                $this->db->trans_rollback();
                                add_error_flash_message('Nepodarilo sa uložiť záznam o odobratí strojového času za produkt <strong>' . $product->title . '</strong>.');
                                redirect(site_url('operations/new_operation'));
                                die();
                            }
                        }
                    }
                    $this->db->trans_commit();
                    add_success_flash_message('Účastníkovi <strong>' . $person->name . ' ' . $person->surname . '</strong> sa úspešne podarilo odobrať <strong>' . $total_time . '</strong> ' . get_inflection_by_numbers((int)$total_time, 'minút', 'minútu', 'minúty', 'minúty', 'minúty', 'minút') . ' strojového času.');
                    redirect(site_url('operations'));
                } else {
                    $this->db->trans_rollback();
                    add_error_flash_message('Účastníkovi <strong>' . $person->name . ' ' . $person->surname . '</strong> sa nepodarilo odobrať <strong>' . $total_time . '</strong> ' . get_inflection_by_numbers((int)$total_time, 'minút', 'minútu', 'minúty', 'minúty', 'minúty', 'minút') . ' strojového času.');
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
            'title' => 'Administrácia / Strojový čas / Prehľad transakcií / ' . $person->name . ' ' . $person->surname,
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
    
    public function batch_time_addition() {
        $this->parser->parse('web/controllers/operations/batch_time_addition.tpl', array(
            'title' => 'Administrácia / Strojový čas / Hromadné pridanie strojového času',
            'form' => $this->get_batch_time_addition_form(),
            'back_url' => site_url('operations'),
        ));
    }
    
    public function do_batch_time_addition() {
        add_common_flash_message('Zatial nie je implementované!!!!');
        redirect(site_url('operations'));
    }
    
    public function get_batch_time_addition_form() {
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
                    'name' => 'batch_time[comment]',
                    'id' => 'batch_time-comment',
                    'label' => 'Komentár',
                    'type' => 'text_input',
                    'placeholder' => 'Sem pridajte komentár, alebo nechajte prázdne.',
                    'hint' => 'Pozor, globálne nastavenie pre všetkých účastníkov.',
                ),
                'workplace' => array(
                    'name' => 'batch_time[workplace_id]',
                    'id' => 'batch_time-workplace_id',
                    'label' => 'Zamestnanie',
                    'type' => 'select',
                    'values' => $workplace_values,
                    'hint' => 'Pozor, globálne nastavenie pre všetkých účastníkov.',
                ),
            ),
            'arangement' => array(
                'workplace', 'comment',
            ),
        );
        
        $persons = new Person();
        $persons->where('admin', '0');
        $persons->order_by('surname', 'asc')->order_by('name', 'asc');
        $persons->get_iterated();
        
        if ($persons->exists()) {
            $form['fields']['persons_divider'] = array(
                'type' => 'divider',
            );
            $form['arangement'][] = 'persons_divider';
        }
        
        foreach ($persons as $person) {
            $form['fields']['person_' . $person->id] = array(
                'name' => 'person_time[' . $person->id . ']',
                'id' => 'person_time-' . $person->id,
                'label' => $person->name . ' ' . $person->surname,
                'type' => 'slider',
                'min' => 0,
                'max' => 240,
                'default' => 0,
                'validation' => array(
                    array(
                        'if-field-not-equals' => array('field' => 'person_time[' . $person->id . ']', 'value' => 0),
                        'rules' => 'required|integer|greater_than[0]',
                    ),
                ),
            );
            $form['arangement'][] = 'person_' . $person->id;
        }
        
        return $form;
    }

    public function get_form($type = '') {
        $operations_addition = new Operation();
        $operations_addition->where('type', 'addition');
        $operations_addition->select_sum('time', 'time_sum');
        $operations_addition->where_related_person('id', '${parent}.id');
        
        $operations_subtraction_simple = new Operation();
        $operations_subtraction_simple->where('type', 'subtraction');
        $operations_subtraction_simple->select_sum('time', 'time_sum');
        $operations_subtraction_simple->where_related_person('id', '${parent}.id');
        
        $operations_subtraction_advanced_1 = new Operation();
        $operations_subtraction_advanced_1->where('type', 'subtraction');
        $operations_subtraction_advanced_1->where_related('product_quantity', 'price >', 0);
        $operations_subtraction_advanced_1->group_start(' NOT', 'AND');
        $operations_subtraction_advanced_1->where_related('product_quantity', 'product_id', NULL);
        $operations_subtraction_advanced_1->group_end();
        unset($operations_subtraction_advanced_1->db->ar_select[0]);
        $operations_subtraction_advanced_1->select_func('SUM', array('@product_quantities.quantity', '*', '@product_quantities.price'), 'time_sum');
        $operations_subtraction_advanced_1->where_related_person('id', '${parent}.id');
        
        $operations_subtraction_advanced_2 = new Operation();
        $operations_subtraction_advanced_2->where('type', 'subtraction');
        $operations_subtraction_advanced_2->where_related('service_usage', 'price >', 0);
        $operations_subtraction_advanced_2->group_start(' NOT', 'AND');
        $operations_subtraction_advanced_2->where_related('service_usage', 'service_id', NULL);
        $operations_subtraction_advanced_2->group_end();
        unset($operations_subtraction_advanced_2->db->ar_select[0]);
        $operations_subtraction_advanced_2->select_func('SUM', array('@service_usages.quantity', '*', '@service_usages.price'), 'time_sum');
        $operations_subtraction_advanced_2->where_related_person('id', '${parent}.id');
        
        $persons = new Person();
        $persons->order_by('surname', 'asc')->order_by('name', 'asc');
        $persons->where('admin', 0);
        $persons->select('*');
        $persons->select_subquery($operations_addition, 'plus_time');
        $persons->select_subquery($operations_subtraction_simple, 'minus_time_1');
        $persons->select_subquery($operations_subtraction_advanced_1, 'minus_time_2');
        $persons->select_subquery($operations_subtraction_advanced_2, 'minus_time_3');
        $persons->include_related('group', 'title');
        $persons->get_iterated();
        
        $persons_select = array('' => '');
        
        foreach ($persons as $person) {
            $time = (intval($person->plus_time) - intval($person->minus_time_1) - intval($person->minus_time_2) - intval($person->minus_time_3));
            $persons_select[$person->id] = $person->name . ' ' . $person->surname . ' (' . $person->group_title . ' | Čas: ' . $time . ' ' . get_inflection_by_numbers($time, 'minút', 'minúta', 'minúty', 'minúty', 'minúty', 'minút') . ')';
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
                    'values' => array(
                        '' => '',
                        'addition' => 'Pridanie strojového času',
                        'subtraction' => 'Odobratie strojového času',
                    ),
                    'validation' => 'required',
                ),
                'person' => array(
                    'name' => 'operation[person_id]',
                    'type' => 'select',
                    'id' => 'operation-person_id',
                    'label' => 'Účastník',
                    'values' => $persons_select,
                    'validation' => 'required',
                ),
                'workplace' => array(
                    'name' => 'operation[workplace_id]',
                    'type' => 'select',
                    'id' => 'operation-workplace_id',
                    'label' => 'Zamestnanie',
                    'values' => $workplaces_select,
                ),
                'comment' => array(
                    'name' => 'operation[comment]',
                    'type' => 'text_input',
                    'id' => 'comment-id',
                    'label' => 'Komentár',
                    'validation' => 'max_length[255]',
                ),
                'time' => array(
                    'name' => 'operation[time]',
                    'type' => 'slider',
                    'id' => 'comment-time',
                    'label' => 'Čas',
                    'min' => 0,
                    'max' => 240,
                    'default' => 0,
                    'validation' => array(
                        array(
                            'if-field-not-equals' => array('field' => 'operation[time]', 'value' => 0),
                            'rules' => 'required|integer|greater_than[0]',
                        ),
                    ),
                ),
            ),
            'arangement' => array(
                'type', 'person', 'workplace', 'comment', 'time'
            ),
        );
        
        if ($type == 'subtraction') {
            $services = new Service();
            $services->order_by('title', 'asc');
            $services->get_iterated();
            
            if ($services->exists()) {
                $form['fields']['services_enable'] = array(
                    'type' => 'flipswitch',
                    'name' => 'services_enable',
                    'id' => 'services_enable',
                    'label' => 'Zobraziť služby',
                    'value_off' => '0',
                    'text_off' => 'Nie',
                    'value_on' => '1',
                    'text_on' => 'Áno',
                );
                $form['fields']['services_divider'] = array(
                    'type' => 'divider',
                    'text' => 'Služby',
                    'class' => 'controlls-services',
                );
                $form['arangement'][] = 'services_enable';
                $form['arangement'][] = 'services_divider';
            }
            
            foreach ($services as $service) {
                $form['fields']['service_' . $service->id . '_quantity'] = array(
                    'name' => 'operation_service[' . $service->id . '][quantity]',
                    'class' => 'controlls-services',
                    'id' => 'operation_service-' . $service->id . '-quantity',
                    'type' => 'slider',
                    'min' => 0,
                    'max' => 240,
                    'label' => $service->title . ' (čas)',
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
                    'default' => $service->price,
                    'validation' => array(
                        array(
                            'if-field-not-equals' => array('field' => 'operation_service[' . $service->id . '][quantity]', 'value' => 0),
                            'rules' => 'required|integer|greater_than[0]',
                        ),
                    ),
                );
                
                $form['arangement'][] = 'service_' . $service->id . '_quantity';
                $form['arangement'][] = 'service_' . $service->id . '_price';
            }

            $quantity_addition = new Product_quantity();
            $quantity_addition->select_sum('quantity', 'quantity_sum');
            $quantity_addition->where('type', 'addition');
            $quantity_addition->where_related('product', 'id', '${parent}.id');

            $quantity_subtraction = new Product_quantity();
            $quantity_subtraction->select_sum('quantity', 'quantity_sum');
            $quantity_subtraction->where('type', 'subtraction');
            $quantity_subtraction->where_related('product', 'id', '${parent}.id');

            $products = new Product();
            $products->order_by('title', 'asc');
            $products->select('*');
            $products->select_subquery($quantity_addition, 'plus_quantity');
            $products->select_subquery($quantity_subtraction, 'minus_quantity');
            $products->get_iterated();
            
            if ($products->exists()) {
                $form['fields']['products_enable'] = array(
                    'type' => 'flipswitch',
                    'name' => 'products_enable',
                    'id' => 'products_enable',
                    'label' => 'Zobraziť produkty',
                    'value_off' => '0',
                    'text_off' => 'Nie',
                    'value_on' => '1',
                    'text_on' => 'Áno',
                );
                $form['fields']['products_divider'] = array(
                    'type' => 'divider',
                    'text' => 'Produkty z bufetu',
                    'class' => 'controlls-products',
                );
                $form['arangement'][] = 'products_enable';
                $form['arangement'][] = 'products_divider';
            }
            
            foreach ($products as $product) {
                $form['fields']['product_' . $product->id . '_quantity'] = array(
                    'name' => 'operation_product[' . $product->id . '][quantity]',
                    'class' => 'controlls-products',
                    'id' => 'operation_product-' . $product->id . '-quantity',
                    'type' => 'slider',
                    'min' => 0,
                    'max' => intval($product->plus_quantity) - intval($product->minus_quantity),
                    'label' => $product->title . ' (počet kusov)',
                    'default' => 0,
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
                    'validation' => array(
                        array(
                            'if-field-not-equals' => array('field' => 'operation_product[' . $product->id . '][quantity]', 'value' => 0),
                            'rules' => 'required|integer|greater_than[0]',
                        ),
                    ),
                );
                
                $form['arangement'][] = 'product_' . $product->id . '_quantity';
                $form['arangement'][] = 'product_' . $product->id . '_price';
            }
        }
        
        if ($type == 'addition') {
            $form['fields']['time']['validation'] = 'required|integer|greater_than[0]';
        } elseif ($type == 'subtraction') {
            $form['fields']['time']['hint'] = 'Voliteľne sem môžete vložiť ďalší strojový čas na dobratie. Pre konkrétne služby a produkty, využite položky nižšie.';
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

?>