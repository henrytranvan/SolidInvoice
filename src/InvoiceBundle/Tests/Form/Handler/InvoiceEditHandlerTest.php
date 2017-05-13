<?php

declare(strict_types=1);

/*
 * This file is part of CSBill project.
 *
 * (c) 2013-2017 Pierre du Plessis <info@customscripts.co.za>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace CSBill\InvoiceBundle\Tests\Form\Handler;

use CSBill\CoreBundle\Response\FlashResponse;
use CSBill\CoreBundle\Templating\Template;
use CSBill\FormBundle\Test\FormHandlerTestCase;
use CSBill\InvoiceBundle\Entity\Invoice;
use CSBill\InvoiceBundle\Form\Handler\InvoiceEditHandler;
use CSBill\InvoiceBundle\Manager\InvoiceManager;
use CSBill\InvoiceBundle\Model\Graph;
use CSBill\MoneyBundle\Entity\Money;
use CSBill\NotificationBundle\Notification\NotificationManager;
use Finite\Factory\FactoryInterface;
use Finite\StateMachine\StateMachineInterface;
use Mockery as M;
use Money\Currency;
use SolidWorx\FormHandler\FormRequest;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class InvoiceEditHandlerTest extends FormHandlerTestCase
{
    private $invoice;

    protected function setUp()
    {
        parent::setUp();

        $this->invoice = new Invoice();
        $this->invoice->setStatus(Graph::STATUS_DRAFT);
        $this->invoice->setDiscount(0.1);
        $this->invoice->setBalance(new \Money\Money(1000, new Currency('USD')));

        $this->em->persist($this->invoice);
        $this->em->flush();

        Money::setBaseCurrency('USD');
    }

    public function getHandler()
    {
        $stateMachine = M::mock(StateMachineInterface::class);
        $stateMachine->shouldReceive('can')
            ->once()
            ->with(Graph::TRANSITION_NEW)
            ->andReturn(true);

        $stateMachine->shouldReceive('can')
            ->once()
            ->with(Graph::TRANSITION_ACCEPT)
            ->andReturn(true);

        $stateMachine->shouldReceive('apply')
            ->with(Graph::TRANSITION_NEW);

        $stateMachine->shouldReceive('apply')
            ->with(Graph::TRANSITION_ACCEPT);

        $factory = M::mock(FactoryInterface::class);
        $factory->shouldReceive('get')
            ->once()
            ->andReturn($stateMachine);

        $notification = M::mock(NotificationManager::class);
        $notification->shouldReceive('sendNotification');

        $invoiceManager = new InvoiceManager($this->registry, new EventDispatcher(), $factory, $notification);

        $router = M::mock(RouterInterface::class);
        $router->shouldReceive('generate')
            ->once()
            ->with('_invoices_view', ['id' => 1])
            ->andReturn('/invoices/1');

        $handler = new InvoiceEditHandler($invoiceManager, $this->em->getRepository('CSBillPaymentBundle:Payment'), $router);
        $handler->setDoctrine($this->registry);

        return $handler;
    }

    protected function assertOnSuccess(?Response $response, $invoice, FormRequest $form)
    {
        /* @var Invoice $invoice */

        $this->assertSame(Graph::STATUS_NEW, $invoice->getStatus());
        $this->assertSame(0.2, $invoice->getDiscount());
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertInstanceOf(FlashResponse::class, $response);
        $this->assertCount(1, $response->getFlash());
        $this->assertCount(1, $this->em->getRepository('CSBillInvoiceBundle:Invoice')->findAll());
    }

    protected function assertResponse(FormRequest $formRequest)
    {
        $this->assertInstanceOf(Template::class, $formRequest->getResponse());
    }

    protected function getHandlerOptions(): array
    {
        return [
            $this->invoice,
            [
                'currency' => new Currency('USD'),
            ],
        ];
    }

    public function getFormData(): array
    {
        return [
            'invoice' => [
                'discount' => 20,
            ],
            'save' => 'pending',
        ];
    }

    protected function getEntityNamespaces(): array
    {
        return [
            'CSBillClientBundle' => 'CSBill\ClientBundle\Entity',
            'CSBillInvoiceBundle' => 'CSBill\InvoiceBundle\Entity',
            'CSBillPaymentBundle' => 'CSBill\PaymentBundle\Entity',
            'CSBillTaxBundle' => 'CSBill\TaxBundle\Entity',
        ];
    }

    protected function getEntities(): array
    {
        return [
            'CSBillClientBundle:Client',
            'CSBillInvoiceBundle:Invoice',
            'CSBillInvoiceBundle:RecurringInvoice',
            'CSBillPaymentBundle:Payment',
            'CSBillTaxBundle:Tax',
        ];
    }
}