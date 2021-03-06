<?php

	/*
	 * To change this template, choose Tools | Templates
	 * and open the template in the editor.
	 */

	/**
	 * Description of products
	 *
	 * @author Andrej
	 * @edit   : Ferdinand Križan
	 */
	class Products extends CI_Controller {

		public function __construct() {
			parent::__construct();
            $this->load->database();
            $this->load->library('datamapper');
			$this->load->library('session');

			auth_redirect_if_not_admin('errormessage/no_admin');
		}

		public function index() {
			$this->load->helper('filter');

			$quantity_addition = new Product_quantity();
			$quantity_addition->select_sum('quantity', 'quantity_sum');
			$quantity_addition->where('type', Product_quantity::TYPE_ADDITION);
			$quantity_addition->where_related('product', 'id', '${parent}.id');

			$quantity_subtraction = new Product_quantity();
			$quantity_subtraction->select_sum('quantity', 'quantity_sum');
			$quantity_subtraction->where('type', Product_quantity::TYPE_SUBTRACTION);
			$quantity_subtraction->where_related('operation', 'type', Operation::TYPE_SUBTRACTION);
			$quantity_subtraction->where_related('operation', 'subtraction_type', Operation::SUBTRACTION_TYPE_PRODUCTS);
			$quantity_subtraction->where_related('product', 'id', '${parent}.id');

			$products = new Product();
			$products->order_by('title', 'asc');
			$products->select('*');
			$products->select_subquery($quantity_addition, 'plus_quantity');
			$products->select_subquery($quantity_subtraction, 'minus_quantity');
			$products->get_iterated();

			$this->parser->parse('web/controllers/products/index.tpl', array(
				'title'        => 'Administrácia / Bufet',
				'new_item_url' => site_url('products/new_product'),
				'products'     => $products,
			));
		}

		public function new_product() {
			$this->parser->parse('web/controllers/products/new_product.tpl', array(
				'title'    => 'Administrácia / Bufet / Nový produkt',
				'back_url' => site_url('products'),
				'form'     => $this->get_product_form(),
			));
		}

		public function create_product() {
			build_validator_from_form($this->get_product_form());
			if ($this->form_validation->run()) {
				$product_data = $this->input->post('product');
				$product      = new Product();
				$product->from_array($product_data, array('title', 'price'));
				if ($product->save()) {
					add_success_flash_message('Produkt <strong>' . $product->title . '</strong> s cenou <strong>' . $product->price . '</strong> a s ID <strong>' . $product->id . '</strong> bol úspešne vytvorený.');
					redirect(site_url('products'));
				} else {
					add_error_flash_message('Produkt <strong>' . $product->title . '</strong> s cenou <strong>' . $product->price . '</strong> sa nepodarilo vytvoriť.');
					redirect(site_url('products/new_product'));
				}
			} else {
				$this->new_product();
			}
		}

		public function edit_product($product_id = NULL) {
			if (is_null($product_id)) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$product = new Product();
			$product->get_by_id((int)$product_id);

			if (!$product->exists()) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$this->parser->parse('web/controllers/products/edit_product.tpl', array(
				'product'  => $product,
				'title'    => 'Administrácia / Bufet / Úprava produktu',
				'form'     => $this->get_product_form(),
				'back_url' => site_url('products'),
			));
		}

		public function update_product($product_id = NULL) {
			if (is_null($product_id)) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$this->db->trans_begin();
			$product = new Product();
			$product->get_by_id((int)$product_id);

			if (!$product->exists()) {
				$this->db->trans_rollback();
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			build_validator_from_form($this->get_product_form());
			if ($this->form_validation->run()) {
				$product_data = $this->input->post('product');
				$product->from_array($product_data, array('title', 'price'));
				if ($product->save() && $this->db->trans_status()) {
					$this->db->trans_commit();
					add_success_flash_message('Produkt s ID <strong>' . $product->id . '</strong> bol úspešne upravený.');
					redirect(site_url('products'));
				} else {
					$this->db->trans_rollback();
					add_error_flash_message('Produk s ID <strong>' . $product->id . '</strong> sa nepodarilo upraviť.');
					redirect(site_url('products/edit_product/' . (int)$product->id));
				}
			} else {
				$this->db->trans_rollback();
				$this->edit_product($product_id);
			}
		}

		public function delete_product($product_id = NULL) {
			if (is_null($product_id)) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$this->db->trans_begin();
			$product = new Product();
			$product->include_related_count('product_quantity', 'product_quantity_count');
			$product->get_by_id((int)$product_id);

			if (!$product->exists()) {
				$this->db->trans_rollback();
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			if ((int)$product->product_quantity_count > 0) {
				$this->db->trans_rollback();
				add_error_flash_message('Produkt nie je možné vymazať, keď už obsahuje záznamy o množstve.');
				redirect(site_url('products'));
			}

			$success_message = 'Produkt <strong>' . $product->title . '</strong> s ID <strong>' . $product->id . '</strong> bol úspešne vymazaný.';
			$error_message   = 'Produkt <strong>' . $product->title . '</strong> s ID <strong>' . $product->id . '</strong> sa nepodarilo vymazať.';

			if ($product->delete() && $this->db->trans_status()) {
				$this->db->trans_commit();
				add_success_flash_message($success_message);
				redirect(site_url('products'));
			} else {
				$this->db->trans_rollback();
				add_error_flash_message($error_message);
				redirect(site_url('products'));
			}
		}

		public function stock($product_id = NULL) {
			if (is_null($product_id)) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$product = new Product();
			$product->get_by_id((int)$product_id);

			if (!$product->exists()) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$product_quantities = new Product_quantity();
			$product_quantities->where_related_product($product);
			$product_quantities->where('type', Product_quantity::TYPE_ADDITION);
			$product_quantities->order_by('created', 'desc');
			$product_quantities->get_iterated();

			$this->parser->parse('web/controllers/products/stock.tpl', array(
				'title'              => 'Administrácia / Bufet / Sklad produktu / ' . $product->title,
				'product_quantities' => $product_quantities,
				'product'            => $product,
				'new_item_url'       => site_url('products/new_product_quantity/' . $product->id),
			));
		}

		public function new_product_quantity($product_id = NULL) {
			if (is_null($product_id)) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$product = new Product();
			$product->get_by_id((int)$product_id);

			if (!$product->exists()) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$this->parser->parse('web/controllers/products/new_product_quantity.tpl', array(
				'product'  => $product,
				'title'    => 'Administrácia / Bufet / Sklad produktu / ' . $product->title . ' / Pridať množstvo',
				'back_url' => site_url('products/stock/' . (int)$product->id),
				'form'     => $this->get_product_quantity_form(),
			));
		}

		public function create_product_quantity($product_id = NULL) {
			if (is_null($product_id)) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$this->db->trans_begin();
			$product = new Product();
			$product->get_by_id((int)$product_id);

			if (!$product->exists()) {
				$this->db->trans_rollback();
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			build_validator_from_form($this->get_product_quantity_form());
			if ($this->form_validation->run()) {
				$product_quantity_data = $this->input->post('product_quantity');
				$product_quantity      = new Product_quantity();
				$product_quantity->from_array($product_quantity_data, array('quantity'));
				$product_quantity->type         = Product_quantity::TYPE_ADDITION;
				$product_quantity->price        = NULL;
				$product_quantity->operation_id = NULL;
				if ($product_quantity->save($product) && $this->db->trans_status()) {
					$this->db->trans_commit();
					add_success_flash_message('Množstvo <strong>' . $product_quantity->quantity . '</strong> ' . get_inflection_by_numbers((int)$product_quantity->quantity, 'kusov', 'kus', 'kusy', 'kusy', 'kusy', 'kusov') . ' bolo pridané k produktu <strong>' . $product->title . '</strong> úspešne.');
					redirect(site_url('products/stock/' . (int)$product->id));
				} else {
					$this->db->trans_rollback();
					add_error_flash_message('Množstvo <strong>' . $product_quantity->quantity . '</strong> ' . get_inflection_by_numbers((int)$product_quantity->quantity, 'kusov', 'kus', 'kusy', 'kusy', 'kusy', 'kusov') . ' sa nepodarilo pridať k produktu <strong>' . $product->title . '</strong>.');
					redirect(site_url('products/new_product_quantity/' . (int)$product->id));
				}
			} else {
				$this->db->trans_rollback();
				$this->new_product_quantity($product->id);
			}
		}

		public function edit_product_quantity($product_id = NULL, $product_quantity_id = NULL) {
			if (is_null($product_id)) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$product = new Product();
			$product->get_by_id((int)$product_id);

			if (!$product->exists()) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			if (is_null($product_quantity_id)) {
				add_error_flash_message('Záznam o množstve tovaru sa nenašiel.');
				redirect(site_url('products/stock/' . (int)$product->id));
			}

			$product_quantity = new Product_quantity();
			$product_quantity->where_related_product($product);
			$product_quantity->where('type', Product_quantity::TYPE_ADDITION);
			$product_quantity->get_by_id((int)$product_quantity_id);

			if (!$product_quantity->exists()) {
				add_error_flash_message('Záznam o množstve tovaru sa nenašiel.');
				redirect(site_url('products/stock/' . (int)$product->id));
			}

			$this->parser->parse('web/controllers/products/edit_product_quantity.tpl', array(
				'product'          => $product,
				'product_quantity' => $product_quantity,
				'title'            => 'Administrácia / Bufet / Sklad produktu / ' . $product->title . ' / Upraviť množstvo',
				'back_url'         => site_url('products/stock/' . (int)$product->id),
				'form'             => $this->get_product_quantity_form(),
			));
		}

		public function update_product_quantity($product_id = NULL, $product_quantity_id = NULL) {
			if (is_null($product_id)) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$this->db->trans_begin();
			$product = new Product();
			$product->get_by_id((int)$product_id);

			if (!$product->exists()) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			if (is_null($product_quantity_id)) {
				add_error_flash_message('Záznam o množstve tovaru sa nenašiel.');
				redirect(site_url('products/stock/' . (int)$product->id));
			}

			$product_quantity = new Product_quantity();
			$product_quantity->where_related_product($product);
			$product_quantity->where('type', Product_quantity::TYPE_ADDITION);
			$product_quantity->get_by_id((int)$product_quantity_id);

			if (!$product_quantity->exists()) {
				add_error_flash_message('Záznam o množstve tovaru sa nenašiel.');
				redirect(site_url('products/stock/' . (int)$product->id));
			}

			build_validator_from_form($this->get_product_quantity_form());
			if ($this->form_validation->run()) {
				$product_quantity_data = $this->input->post('product_quantity');
				$product_quantity->from_array($product_quantity_data, array('quantity'));
				if ($product_quantity->save()) {
					$product_quantities_addition = new Product_quantity();
					$product_quantities_addition->where('type', Product_quantity::TYPE_ADDITION);
					$product_quantities_addition->where_related_product($product);
					$product_quantities_addition->select_func('SUM', array('@quantity'), 'quantity_sum');
					$product_quantities_addition->get();

					$product_quantities_subtraction = new Product_quantity();
					$product_quantities_subtraction->where('type', Product_quantity::TYPE_SUBTRACTION);
					$product_quantities_subtraction->where_related_product($product);
					$product_quantities_subtraction->select_func('SUM', array('@quantity'), 'quantity_sum');
					$product_quantities_subtraction->get();

					if ((int)$product_quantities_addition->quantity_sum >= (int)$product_quantities_subtraction->quantity_sum) {
						$this->db->trans_commit();
						add_success_flash_message('Množstvo úspešne aktualizované na <strong>' . $product_quantity->quantity . '</strong> ' . get_inflection_by_numbers((int)$product_quantity->quantity, 'kusov', 'kus', 'kusy', 'kusy', 'kusy', 'kusov') . ' v záznamoch o produkte <strong>' . $product->title . '</strong>.');
						redirect(site_url('products/stock/' . (int)$product->id));
					} else {
						$this->db->trans_rollback();
						add_error_flash_message('Nové množstvo spôsobuje, že je v celkovej evidencii menej produktov pridaných ako predaných. Množstvo nemôže byť aktualizované na <strong>' . $product_quantity->quantity . '</strong>.');
						redirect(site_url('products/edit_product_quantity/' . (int)$product->id . '/' . (int)$product_quantity->id));
					}
				} else {
					$this->db->trans_rollback();
					add_error_flash_message('Nepodarilo sa aktualizovať množstvo produktu <strong>' . $product->title . '</strong>.');
					redirect(site_url('products/edit_product_quantity/' . (int)$product->id . '/' . (int)$product_quantity->id));
				}
			} else {
				$this->db->trans_rollback();
				$this->edit_product_quantity($product_id, $product_quantity_id);
			}
		}

		public function batch_stock_addition() {
			$this->load->helper('filter');
			$this->parser->parse('web/controllers/products/batch_stock_addition.tpl', array(
				'title'    => 'Administrácia / Bufet / Hromadné pridanie zásob',
				'back_url' => site_url('products'),
				'form'     => $this->get_batch_stock_addition_form(),
			));
		}

		public function do_batch_stock_addition() {
			$this->db->trans_begin();
			build_validator_from_form($this->get_batch_stock_addition_form());
			if ($this->form_validation->run()) {
				$products = new Product();
				$products->get_iterated();

				$product_quantity_data = $this->input->post('product_quantity_addition');

				$success_messages = array();
				$error_messages   = array();
				$added            = 0;
				$failed           = 0;

				foreach ($products as $product) {
					if (isset($product_quantity_data[$product->id]['quantity']) && (int)$product_quantity_data[$product->id]['quantity'] > 0) {
						$product_quantity               = new Product_quantity();
						$product_quantity->type         = Product_quantity::TYPE_ADDITION;
						$product_quantity->quantity     = (int)$product_quantity_data[$product->id]['quantity'];
						$product_quantity->price        = NULL;
						$product_quantity->operation_id = NULL;
						if ($product_quantity->save($product) && $this->db->trans_status()) {
							$added++;
							$success_messages[] = 'Produktu <strong>' . $product->title . '</strong> ' . get_inflection_by_numbers((int)$product_quantity->quantity, 'bolo pridaných', 'bol pridaný', 'boli pridané', 'boli pridané', 'boli pridané', 'bolo pridaných') . ' <strong>' . $product_quantity->quantity . '</strong> ' . get_inflection_by_numbers((int)$product_quantity->quantity, 'kusov', 'kus', 'kusy', 'kusy', 'kusy', 'kusov') . ' zásob na sklad.';
						} else {
							$failed++;
							$error_messages[] = 'Produktu <strong>' . $product->title . '</strong> sa nepodarilo pridať <strong>' . $product_quantity->quantity . '</strong> ' . get_inflection_by_numbers((int)$product_quantity->quantity, 'kusov', 'kus', 'kusy', 'kusy', 'kusy', 'kusov') . ' zásob na sklad.';
						}
					}
				}
				if ($added == 0 && $failed == 0) {
					$this->db->trans_rollback();
					add_common_flash_message('Nebolo nič pridané, keďže bol odoslaný prázdny formulár.');
					redirect(site_url('products'));
				} elseif ($added == 0 && $failed > 0) {
					$this->db->trans_rollback();
					add_error_flash_message('Nepodarilo sa pridať žiadne zásoby na sklad:<br /><br />' . implode('<br />', $error_messages));
					redirect(site_url('products/batch_stock_addition'));
				} else {
					$this->db->trans_commit();
					if ($added > 0) {
						add_success_flash_message('Boli pridané zásoby k celkovo <strong>' . $added . '</strong> ' . get_inflection_by_numbers($added, 'produktom', 'produktu', 'produktom', 'produktom', 'produktom', 'produktom') . ':<br /><br />' . implode('<br />', $success_messages));
					}
					if ($failed > 0) {
						add_error_flash_message('K <strong>' . $failed . '</strong> ' . get_inflection_by_numbers($failed, 'produktom', 'produktu', 'produktom', 'produktom', 'produktom', 'produktom') . ' sa nepodarilo pridat zásoby:<br /><br />' . implode('<br />', $error_messages));
					}
					redirect(site_url('products'));
				}
			} else {
				$this->db->trans_rollback();
				$this->batch_stock_addition();
			}
		}

		public function overview($product_id = NULL) {
			if (is_null($product_id)) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$product = new Product();
			$product->get_by_id((int)$product_id);

			if (!$product->exists()) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$product_quantities = new Product_quantity();
			$product_quantities->where_related_product($product);
			$product_quantities->include_related('operation', array('id', 'type', 'created'));
			$product_quantities->include_related('operation/person', array('name', 'surname'));
			$product_quantities->include_related('operation/admin', array('name', 'surname'));
			$product_quantities->include_related('operation/workplace', array('title'));
			$product_quantities->order_by('created', 'desc');
			$product_quantities->order_by_related('operation', 'created', 'desc');
			$product_quantities->get_iterated();

			$this->parser->parse('web/controllers/products/overview.tpl', array(
				'title'              => 'Administrácia / Bufet / Prehľad o produkte / ' . $product->title,
				'product'            => $product,
				'product_quantities' => $product_quantities,
				'back_url'           => site_url('products'),
			));
		}

		public function _ok($str) {
			return TRUE;
		}

		public function edit_photo($product_id = NULL) {
			if (is_null($product_id)) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$product = new Product();
			$product->get_by_id((int)$product_id);

			if (!$product->exists()) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$current_photo = base_url('user/products/data/' . (int)$product->id . '/product.png');
			if (!file_exists('user/products/data/' . (int)$product->id . '/product.png')) {
				$current_photo = base_url('user/products/default/product.png');
			}

			$this->parser->parse('web/controllers/products/edit_photo.tpl', array(
				'title'    => 'Administrácia / Bufet / Fotografia',
				'back_url' => site_url('products'),
				'form'     => $this->get_photo_edit_form($current_photo),
				'product'  => $product,
			));
		}

		public function upload_photo($product_id = NULL) {
			if (is_null($product_id)) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}

			$product = new Product();
			$product->get_by_id((int)$product_id);

			if (!$product->exists()) {
				add_error_flash_message('Produkt sa nenašiel.');
				redirect(site_url('products'));
			}


			$upload_config = array(
				'upload_path'   => 'user/products/data/' . (int)$product->id . '/',
				'allowed_types' => 'jpg|png',
				'max_size'      => '1024',
				'max_width'     => '1024',
				'max_height'    => '1024',
				'file_name'     => 'temp_product.png',
				'overwrite'     => TRUE,
			);
			$this->load->library('upload', $upload_config);
			@mkdir($upload_config['upload_path'], DIR_WRITE_MODE, TRUE);

			if ($this->upload->do_upload('photo')) {
				$resize_config = array(
					'image_library'  => 'gd2',
					'source_image'   => $upload_config['upload_path'] . $upload_config['file_name'],
					'create_thumb'   => FALSE,
					'maintain_ratio' => TRUE,
					'width'          => 256,
					'height'         => 256,
					'quality'        => '90%',
					'new_image'      => $upload_config['upload_path'] . 'product.png',
				);
				$this->load->library('image_lib', $resize_config);
				if ($this->image_lib->resize()) {
					$resize_config['width']     = 64;
					$resize_config['height']    = 64;
					$resize_config['new_image'] = $upload_config['upload_path'] . 'product_min.png';
					@unlink($upload_config['new_image']);
					$this->image_lib->initialize($resize_config);
					$this->image_lib->resize();
					@unlink($resize_config['source_image']);
					add_success_flash_message('Súbor úspešne nahraný.');
					redirect(site_url('products/edit_photo/' . (int)$product->id));
				} else {
					@unlink($resize_config['source_image']);
					add_error_flash_message('Súbor sa nepodarilo preškálovať:' . $this->image_lib->display_errors('<br /><br />', ''));
					redirect(site_url('products/edit_photo/' . (int)$product->id));
				}
			} else {
				add_error_flash_message('Súbor sa nepodarilo nahrať, vznikla nasledujúca chyba:' . $this->upload->display_errors('<br /><br />', ''));
				redirect(site_url('products/edit_photo/' . (int)$product->id));
			}
		}

		protected function get_product_form() {
			$form = array(
				'fields'     => array(
					'title' => array(
						'name'            => 'product[title]',
						'id'              => 'product-title',
						'type'            => 'text_input',
						'label'           => 'Názov',
						'object_property' => 'title',
						'validation'      => 'required',
					),
					'price' => array(
						'name'            => 'product[price]',
						'id'              => 'product-price',
						'type'            => 'text_input',
						'label'           => 'Cena',
						'object_property' => 'price',
						'hint'            => 'Cena musí byť číslo vačšie ako nula. Cena je násobená kurzom, ktorý vyjadruje počet LEDCOIN-ov za Horalku.',
						'validation'      => 'required|number|greater_than[0]',
					),
				),
				'arangement' => array('title', 'price',),
			);

			return $form;
		}

		protected function get_product_quantity_form() {
			$form = array(
				'fields'     => array(
					'quantity' => array(
						'name'            => 'product_quantity[quantity]',
						'id'              => 'product_quantity-quantity',
						'label'           => 'Množstvo',
						'hint'            => 'Množstvo musí byť vačšie ako 0.',
						'type'            => 'text_input',
						'validation'      => 'required|integer|greater_than[0]',
						'object_property' => 'quantity',
					),
				),
				'arangement' => array('quantity',),
			);

			return $form;
		}

		protected function get_batch_stock_addition_form() {
			$products = new Product();
			$products->order_by('title', 'asc');
			$products->get_iterated();

			$form_fields     = array();
			$form_arangement = array();

			foreach ($products as $product) {
				$form_fields['product_' . $product->id] = array(
					'name'        => 'product_quantity_addition[' . $product->id . '][quantity]',
					'data'        => array('product-name' => $product->title,),
					'id'          => 'product_quantity_addition-' . $product->id,
					'label'       => '<span class="product_title_label"><img src="' . get_product_image_min($product->id) . '" alt="" /><span class="product_title">' . $product->title . '</span></span>',
					'placeholder' => 'Nechajte prázdne, ak nie je čo pridať.',
					'type'        => 'text_input',
					'validation'  => array(
						array(
							'if-field-not-equals' => array(
								'field' => 'product_quantity_addition[' . $product->id . '][quantity]',
								'value' => '',
							),
							'rules'               => 'required|integer|greater_than[0]',
						),
						array(
							'if-field-equals' => array(
								'field' => 'product_quantity_addition[' . $product->id . '][quantity]',
								'value' => '',
							),
							'rules'           => 'callback__ok',
						),
					),
				);
				$form_arangement[]                      = 'product_' . $product->id;
			}

			return array('fields' => $form_fields, 'arangement' => $form_arangement);
		}

		protected function get_photo_edit_form($current_photo) {
			$form = array(
				'fields'     => array(
					'current_photo' => array(
						'type'  => 'imagepreview',
						'label' => 'Súčasná fotografia',
						'path'  => $current_photo,
					),
					'photo'         => array(
						'type'  => 'upload',
						'label' => 'Nová fotografia',
						'name'  => 'photo',
						'id'    => 'photo',
						'hint'  => 'Fotografia vo formáte jpg alebo png.',
					),
				),
				'arangement' => array('current_photo', 'photo',),
			);

			return $form;
		}

	}

