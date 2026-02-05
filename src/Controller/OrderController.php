<?php

namespace App\Controller;

use App\Entity\Order;
use App\Form\OrderType;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/BOrder')]
final class OrderController extends AbstractController
{
    #[Route(name: 'app_order_back', methods: ['GET', 'POST'])]
    public function back(
        Request $request,
        OrderRepository $orderRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $orders = $orderRepository->findAll();

        $orderId = $request->query->getInt('id', 0);
        if ($orderId > 0) {
            $order = $orderRepository->find($orderId);
            if (!$order) {
                throw $this->createNotFoundException('Order not found');
            }
        } else {
            $order = new Order();
        }

        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isNew = $order->getId() === null;
            if ($isNew) {
                $entityManager->persist($order);
            }
            $entityManager->flush();

            $this->addFlash('success', $isNew ? 'Order created successfully.' : 'Order updated successfully.');

            return $this->redirectToRoute('app_order_back', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('order/back.html.twig', [
            'orders' => $orders,
            'form' => $form,
            'editing' => $order->getId() !== null,
            'currentOrder' => $order,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_order_delete', methods: ['POST'])]
    public function delete(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($order);
            $entityManager->flush();
            $this->addFlash('success', 'Order deleted successfully.');
        }

        return $this->redirectToRoute('app_order_back', [], Response::HTTP_SEE_OTHER);
    }
}
