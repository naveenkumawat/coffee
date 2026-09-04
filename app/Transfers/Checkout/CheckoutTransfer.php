<?php

namespace App\Transfers\Checkout;

use App\Transfers\AbstractTransfer;

class CheckoutTransfer extends AbstractTransfer implements CheckoutTransferInterface
{
    protected ?string $checkoutToken = null;

    protected ?string $customerName = null;

    protected ?string $customerEmail = null;

    protected ?string $customerPhone = null;

    protected ?string $pickupName = null;

    protected ?string $pickupPhone = null;

    protected ?string $customerNotes = null;

    protected ?string $pickupNotes = null;

    protected ?string $fulfilmentMethod = null;

    protected ?string $deliveryAddress = null;

    protected ?string $deliveryPhone = null;

    protected ?string $deliveryContactName = null;

    protected ?string $deliveryNotes = null;

    protected ?int $deliveryAddressId = null;

    protected ?string $addressLine1 = null;

    protected ?string $addressLine2 = null;

    protected ?string $landmark = null;

    protected ?string $city = null;

    protected ?string $state = null;

    protected ?string $postalCode = null;

    protected ?string $addressLabel = null;

    protected bool $saveDeliveryAddress = false;

    protected bool $makeDefaultAddress = false;

    protected ?int $cafeTableId = null;

    protected ?string $paymentMethod = null;

    public function getCheckoutToken(): ?string
    {
        return $this->checkoutToken;
    }

    public function setCheckoutToken(?string $checkoutToken): void
    {
        $this->checkoutToken = $checkoutToken;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(?string $customerName): void
    {
        $this->customerName = $customerName;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(?string $customerEmail): void
    {
        $this->customerEmail = $customerEmail;
    }

    public function getCustomerPhone(): ?string
    {
        return $this->customerPhone;
    }

    public function setCustomerPhone(?string $customerPhone): void
    {
        $this->customerPhone = $customerPhone;
    }

    public function getPickupName(): ?string
    {
        return $this->pickupName;
    }

    public function setPickupName(?string $pickupName): void
    {
        $this->pickupName = $pickupName;
    }

    public function getPickupPhone(): ?string
    {
        return $this->pickupPhone;
    }

    public function setPickupPhone(?string $pickupPhone): void
    {
        $this->pickupPhone = $pickupPhone;
    }

    public function getCustomerNotes(): ?string
    {
        return $this->customerNotes;
    }

    public function setCustomerNotes(?string $customerNotes): void
    {
        $this->customerNotes = $customerNotes;
    }

    public function getPickupNotes(): ?string
    {
        return $this->pickupNotes;
    }

    public function setPickupNotes(?string $pickupNotes): void
    {
        $this->pickupNotes = $pickupNotes;
    }

    public function getFulfilmentMethod(): ?string
    {
        return $this->fulfilmentMethod;
    }

    public function setFulfilmentMethod(?string $fulfilmentMethod): void
    {
        $this->fulfilmentMethod = $fulfilmentMethod;
    }

    public function getDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(?string $deliveryAddress): void
    {
        $this->deliveryAddress = $deliveryAddress;
    }

    public function getDeliveryPhone(): ?string
    {
        return $this->deliveryPhone;
    }

    public function setDeliveryPhone(?string $deliveryPhone): void
    {
        $this->deliveryPhone = $deliveryPhone;
    }

    public function getDeliveryContactName(): ?string
    {
        return $this->deliveryContactName;
    }

    public function setDeliveryContactName(?string $deliveryContactName): void
    {
        $this->deliveryContactName = $deliveryContactName;
    }

    public function getDeliveryNotes(): ?string
    {
        return $this->deliveryNotes;
    }

    public function setDeliveryNotes(?string $deliveryNotes): void
    {
        $this->deliveryNotes = $deliveryNotes;
    }

    public function getDeliveryAddressId(): ?int
    {
        return $this->deliveryAddressId;
    }

    public function setDeliveryAddressId(?int $deliveryAddressId): void
    {
        $this->deliveryAddressId = $deliveryAddressId;
    }

    public function getAddressLine1(): ?string
    {
        return $this->addressLine1;
    }

    public function setAddressLine1(?string $addressLine1): void
    {
        $this->addressLine1 = $addressLine1;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function setAddressLine2(?string $addressLine2): void
    {
        $this->addressLine2 = $addressLine2;
    }

    public function getLandmark(): ?string
    {
        return $this->landmark;
    }

    public function setLandmark(?string $landmark): void
    {
        $this->landmark = $landmark;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): void
    {
        $this->state = $state;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): void
    {
        $this->postalCode = $postalCode;
    }

    public function getAddressLabel(): ?string
    {
        return $this->addressLabel;
    }

    public function setAddressLabel(?string $addressLabel): void
    {
        $this->addressLabel = $addressLabel;
    }

    public function getSaveDeliveryAddress(): bool
    {
        return $this->saveDeliveryAddress;
    }

    public function setSaveDeliveryAddress(bool $saveDeliveryAddress): void
    {
        $this->saveDeliveryAddress = $saveDeliveryAddress;
    }

    public function getMakeDefaultAddress(): bool
    {
        return $this->makeDefaultAddress;
    }

    public function setMakeDefaultAddress(bool $makeDefaultAddress): void
    {
        $this->makeDefaultAddress = $makeDefaultAddress;
    }

    public function getCafeTableId(): ?int
    {
        return $this->cafeTableId;
    }

    public function setCafeTableId(?int $cafeTableId): void
    {
        $this->cafeTableId = $cafeTableId;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function toArray(): array
    {
        return [
            'checkout_token' => $this->checkoutToken,
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'customer_phone' => $this->customerPhone,
            'pickup_name' => $this->pickupName,
            'pickup_phone' => $this->pickupPhone,
            'customer_notes' => $this->customerNotes,
            'pickup_notes' => $this->pickupNotes,
            'fulfilment_method' => $this->fulfilmentMethod,
            'delivery_address' => $this->deliveryAddress,
            'delivery_phone' => $this->deliveryPhone,
            'delivery_contact_name' => $this->deliveryContactName,
            'delivery_notes' => $this->deliveryNotes,
            'delivery_address_id' => $this->deliveryAddressId,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'landmark' => $this->landmark,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
            'address_label' => $this->addressLabel,
            'save_delivery_address' => $this->saveDeliveryAddress,
            'make_default_address' => $this->makeDefaultAddress,
            'cafe_table_id' => $this->cafeTableId,
            'payment_method' => $this->paymentMethod,
        ];
    }
}
