<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Controller\ProductRepository;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
function compareProductIds($a, $b) {
    return $a['similarity'] <=> $b['similarity'];
}
class ProductController extends AbstractController
{
    private $params;
    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }
    #[Route('/product_create', name: 'create_product')]
    public function createProduct(EntityManagerInterface $entityManager): Response
    {
        
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, TRUE);
        $name = $input["name"];
        $number_in_stock = $input["number_in_stock"];
        $add_time = $input["add_time"];
        $price = $input["price"];

        $product = new Product();
        $product->setName($name);
        $product->setNumberInStock($number_in_stock);
        #$product->setAddTime(date("Y-m-d H:i:s"));
        $product->setAddTime($add_time);
        $product->setPrice($price);

        // tell Doctrine you want to (eventually) save the Product (no queries yet)
        $entityManager->persist($product);

        // actually executes the queries (i.e. the INSERT query)
        $entityManager->flush();

        $productRepository = $entityManager->getRepository(Product::class);
        $new_product = $productRepository->findOneByMaxId();
        $new_product_info = [
            'id' => $new_product->getId(),
            'name' => $new_product->getName(),
            'number_in_stock' => $new_product->getNumberInStock(),
            'price' => $new_product->getPrice()
        ];
        //return new Response(json_encode(["id" => $$new_product->getId()]));
        //return $this->redirectToRoute('edit-product', $new_product_info);
        return new JsonResponse(['id' => $new_product_info['id']]);
    }

    #[Route('/product/{id}', name: 'show_product')]
    public function show(EntityManagerInterface $entityManager, int $id): Response
    {
        $product = $entityManager->getRepository(Product::class)->findProductById($id);
        if(!$product)
        {
            throw $this->createNotFoundException(
                'No product found for id '.$id
            );
        }

        return $this->render('product_show.html.twig', [
            $product
        ]);
    }

    #[Route('/product_list', name: 'show_All_products')]
    public function showAllProducts(EntityManagerInterface $entityManager): Response
    {
        $products = $entityManager->getRepository(Product::class)->findAllProducts();
        $form = $this->createForm(ProductType::class, new Product());

        $product_list = '';

        foreach ($products as $product) 
        {
            $product_list .= '<div>' . $product->getName() . ' ' . $product->getNumberInStock() . ' ' . $product->getAddTime() . ' ' . $product->getPrice() . '</div>' . '<br>';

        }
        return $this->render('product_list.html.twig', [
            'products' => $products,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->params->get('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->params->get('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->params->get('CONTENT_MAX_LENGTH'),
            'form' => $form
        ]);
    }

    #[Route('/product_edit/{id}', name: 'edit_product')]
    public function edit(EntityManagerInterface $entityManager, $id, Request $request): Response
    {
        $productRepository = $entityManager->getRepository(Product::class);
        $product = $productRepository->find($id);
        
        // Render the form view
        return $this->render('product_edit.html.twig', [
            'product' => $product,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->params->get('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->params->get('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->params->get('CONTENT_MAX_LENGTH'),
        ]);
    }
    #[Route('/product_save/{id}', name: 'save_product', methods: ['POST'])]
    public function saveProduct(Request $request, EntityManagerInterface $entityManager, $id, LoggerInterface $logger): Response
    {
        try {
            $productRepository = $entityManager->getRepository(Product::class);
            $product = $productRepository->find($id);

            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, TRUE);
            $name = $input["name"];
            $category = $input["kategory"];
            $description = $input["description"];
            $number_in_stock = $input["number_in_stock"];
            $image_url = $input["image_url"];
            $width = $input["width"];
            $height = $input["height"];
            $length = $input["length"];
            $weight = $input["weight"];
            $material = $input["material"];
            $color = $input["color"];
            $price = $input["price"];

            $product->setName($name);
            $product->setKategory($category);
            $product->setDescription($description);
            $product->setNumberInStock($number_in_stock);
            $product->setImageUrl($image_url);
            $product->setWidth($width);
            $product->setHeight($height);
            $product->setLength($length);
            $product->setWeight($weight);
            $product->setMaterial($material);
            $product->setColor($color);
            $product->setPrice($price);

            // tell Doctrine you want to (eventually) save the Product (no queries yet)
            $entityManager->persist($product);

            // actually executes the queries (i.e. the INSERT query)
            $entityManager->flush();
            
            // Render the form view
            return new JsonResponse(["status" => "Success"]);
        } catch (Exception $e) {
            // Log the error
            $logger->error('An error occurred: ' . $e->getMessage());
            // Optionally, you can log the stack trace as well
            $logger->error('Stack trace: ' . $e->getTraceAsString());
        }
        
    }
    #[Route('/product_delete/{id}', name: 'delete_product', methods: ['DELETE'])]
    public function deleteProduct($id,  EntityManagerInterface $entityManager): Response
    {
        $productRepository = $entityManager->getRepository(Product::class);
        $product = $productRepository->find($id);

        if ($product) {
            $productRepository->deleteProduct($product);
            return new JsonResponse();
        } else {
            return new JsonResponse();
        }
    }
    #[Route('/image_save', name: 'save_image')]
    public function saveImage(Request $request, EntityManagerInterface $entityManager): Response
    {
        $response = [];

        $file = $request->files->get('myFile');
        $name = $request->request->get('name');

        if ($file && $name) {
            $uploadDir = 'images/';

            $fileExtension = $file->getClientOriginalExtension();
            $uniqueFileName = $name . '.' . $fileExtension;

            $uploadFilePath = $uploadDir . $uniqueFileName;

            try {
                $file->move($uploadDir, $uniqueFileName);
                $response['success'] = true;
                $response['filePath'] = '../' . $uploadFilePath;
            } catch (FileException $e) {
                $response['error'] = 'File upload failed: ' . $e->getMessage();
            }
        } else {
            $response['error'] = 'No file or name provided';
        }
        return new JsonResponse($response);
    }
    
    #[Route('/search', name: 'search', methods: ['POST'])]
    public function search(EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, TRUE);
        $query = $input["query"];
        $command = 'python ../python_scripts/tf-idf.py "' . $query . '"';
        $output = shell_exec($command);

        $results = json_decode($output, true);
        
        $session->set('search_results', $results);

        // Redirect to the results route
        //return $this->redirectToRoute('results');
        return new JsonResponse(["results" => $results]);
    }
    #[Route('/results', name: 'results')]
    public function results(SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $output = $session->get('search_results', '');
        // This method will render the results.html.twig template
        $ids = array_column($output, 'product_id');
        $productRepository = $entityManager->getRepository(Product::class);
        $products = [];
        foreach($ids as $id) {
            $product = $productRepository->find($id);
            $products[] = $product;
        }

        return $this->render('results.html.twig', [
            'products' => $products,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->params->get('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->params->get('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->params->get('CONTENT_MAX_LENGTH'),
        ]);
    }
    
}
