<?php

    namespace IOL\Shop\v1\Entity;

    use IOL\Shop\v1\DataSource\Database;
    use IOL\Shop\v1\DataType\Date;
    use IOL\Shop\v1\DataType\UUID;
    use IOL\Shop\v1\Exceptions\InvalidValueException;
    use IOL\Shop\v1\Exceptions\NotFoundException;

    class Category
    {
        public const DB_TABLE = 'categories';

        private int $id;
        private string $title;
        private ?string $description;

        private array $products = [];

        public function __construct(?int $id = null)
        {
            if (!is_null($id)) {
                if (!is_int($id)) {
                    throw new InvalidValueException('Invalid Category-ID');
                }
                $this->loadData(Database::getRow('id', $id, self::DB_TABLE));
            }
        }

        private function loadData(array|false $values)
        {

            if (!$values || count($values) === 0) {
                throw new NotFoundException('Category could not be loaded');
            }

            $this->id = $values['id'];
            $this->title = $values['title'];
            $this->description = $values['description'];
        }

        public function loadProducts()
        {
            $database = \IOL\Shop\v1\DataSource\Database::getInstance();

            $data = $database->query('SELECT * FROM ' . Product::DB_TABLE . ' WHERE category_id = ' . $this->id . ' AND 
            (show_from <= NOW() OR show_from IS NULL) AND (show_until >= NOW() OR show_until IS NULL)
            ORDER BY sort ASC');

            foreach($data as $productData){
                $product = new Product();
                $product->loadData($productData);

                $this->addProduct($product);
            }

        }

        public function addProduct(Product $product){
            $this->products[] = $product;
        }

        /**
         * @return array
         */
        public function getProducts(): array
        {
            return $this->products;
        }


    }
