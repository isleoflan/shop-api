<?php

    namespace IOL\Shop\v1\Entity;

    use IOL\Shop\v1\DataSource\Database;
    use IOL\Shop\v1\DataType\Date;
    use IOL\Shop\v1\DataType\UUID;
    use IOL\Shop\v1\Exceptions\InvalidValueException;
    use IOL\Shop\v1\Exceptions\NotFoundException;
    use JetBrains\PhpStorm\Pure;

    class Product
    {
        public const DB_TABLE = 'products';

        private string $id;
        private Category $category;
        private ?string $number;
        private string $title;
        private ?string $description;
        private int $price;
        private ?Date $showFrom;
        private ?Date $showUntil;
        private ?array $additionalData;
        private array $media = [];
        private int $sort;

        /**
         * @throws \IOL\Shop\v1\Exceptions\InvalidValueException
         */
        public function __construct(?string $id = null)
        {
            if (!is_null($id)) {
                if (!UUID::isValid($id)) {
                    throw new InvalidValueException('Invalid Product-ID');
                }
                $this->loadData(Database::getRow('id', $id, self::DB_TABLE));
            }
        }

        public function loadData(array|false $values)
        {

            if (!$values || count($values) === 0) {
                throw new NotFoundException('App could not be loaded');
            }

            $this->id = $values['id'];
            $this->category = new Category($values['category_id']);
            $this->number = $values['product_number'];
            $this->title = $values['title'];
            $this->description = $values['description'];
            $this->price = $values['price'];
            $this->showFrom = is_null($values['show_from']) ? null : new Date($values['show_from']);
            $this->showUntil = is_null($values['show_until']) ? null : new Date($values['show_until']);
            $this->additionalData = json_decode($values['additional_data'], true);
            $this->sort = $values['sort'];

            $this->loadMedia();
        }

        public function loadMedia(): void
        {
            $database = Database::getInstance();
            $database->where('product_id', $this->id);
            $database->orderBy('sort');
            $data = $database->get(ProductMedium::DB_TABLE, [0, 1]);
            foreach($data as $medium){
                $productMedium = new ProductMedium();
                $productMedium->loadData($medium);
                $this->media[] = $productMedium;
            }
        }

        #[Pure]
        public function getImages(): array
        {
            $return = [];
            /** @var ProductMedium $medium */
            foreach($this->media as $medium){
                if($medium->getType() == 'IMAGE') {
                    $return[] = $medium->getUrl();
                }
            }
            return $return;
        }

        public function getVariants(): array
        {
            return []; //TODO
        }

        /**
         * @return string
         */
        public function getId(): string
        {
            return $this->id;
        }

        /**
         * @return string
         */
        public function getTitle(): string
        {
            return $this->title;
        }

        /**
         * @return string
         */
        public function getDescription(): string
        {
            return $this->description;
        }

        /**
         * @return int
         */
        public function getPrice(): int
        {
            return $this->price;
        }

        /**
         * @return array
         */
        public function getAdditionalData(): array
        {
            return $this->additionalData;
        }

        /**
         * @return int
         */
        public function getSort(): int
        {
            return $this->sort;
        }

    }
