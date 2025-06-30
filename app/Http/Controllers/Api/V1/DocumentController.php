<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Throwable;

class DocumentController extends Controller
{
    public function __construct(protected DocumentService $documentService)
    {
    }

    /**
     * Wyświetla listę dokumentów z zaawansowanym filtrowaniem, sortowaniem i paginacją.
     */

    public function index(Request $request): JsonResource
    {
        // 1. Walidacja została zastąpiona przez "białe listy" w QueryBuilder.
        //    Jest to bezpieczniejsze i bardziej deklaratywne.

        $documents = QueryBuilder::for(Document::class, $request)
            // 2. Dołączanie relacji i zliczeń, które są zawsze potrzebne.
            ->with([
                'user:id,name',
                'responsible:id,name',
                'supplier:id,name',
                'customer:id,name',
                'sourceWarehouse:id,name',
                'targetWarehouse:id,name',
                'parentDocument:id,number',
            ])
            ->withCount(['items', 'media as attachments_count'])

            // 3. Definicja dozwolonych filtrów.
            ->allowedFilters([
                // Filtry proste (dokładne dopasowanie)
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('responsible_id'),
                AllowedFilter::exact('related_order_id'),

                // Filtry oparte na scope'ach zdefiniowanych w modelu Document
                AllowedFilter::scope('search'),
                AllowedFilter::scope('product_id', 'whereHasVariant'),
                AllowedFilter::scope('related_document_number', 'whereRelatedDocumentNumber'),

                // Filtr dla tablicy typów (np. filter[type][]=PZ&filter[type][]=WZ)
                AllowedFilter::callback('type', fn($query, $value) => $query->whereIn('type', (array) $value)),

                // Filtr dla statusu otwarty/zamknięty
                AllowedFilter::callback('open_closed_status', function ($query, $value) {
                    if ($value === 'open') {
                        $query->whereNull('closed_at');
                    } elseif ($value === 'closed') {
                        $query->whereNotNull('closed_at');
                    }
                }),

                // Filtry dla zakresów dat (obsługują format: filter[issue_date_from]=... & filter[issue_date_to]=...)
                AllowedFilter::scope('issue_date_from'),
                AllowedFilter::scope('issue_date_to'),
                AllowedFilter::scope('delivery_date_from'),
                AllowedFilter::scope('delivery_date_to'),
                AllowedFilter::scope('payment_date_from'),
                AllowedFilter::scope('payment_date_to'),
                AllowedFilter::scope('created_at_from'),
                AllowedFilter::scope('created_at_to'),
                AllowedFilter::scope('closed_at_from'),
                AllowedFilter::scope('closed_at_to'),
            ])

            // 4. Definicja dozwolonych sortowań.
            ->allowedSorts([
                // Proste sortowanie po kolumnach z tabeli `documents`
                'id',
                'number',
                'document_date',
                'total_gross',
                'created_at',

                // Sortowanie po polach z relacji za pomocą callbacków
                AllowedSort::callback('supplier_name', function ($query, $descending) {
                    $direction = $descending ? 'desc' : 'asc';
                    $query->leftJoin('suppliers', 'documents.supplier_id', '=', 'suppliers.id')
                        ->orderBy('suppliers.name', $direction)->select('documents.*');
                }),
                AllowedSort::callback('customer_name', function ($query, $descending) {
                    $direction = $descending ? 'desc' : 'asc';
                    $query->leftJoin('customers', 'documents.customer_id', '=', 'customers.id')
                        ->orderBy('customers.name', $direction)->select('documents.*');
                }),
            ])

            // 5. Domyślne sortowanie, jeśli nie podano innego.
            ->defaultSort('-document_date', '-id');

        // 6. Paginacja wyników.
        $perPage = $request->input('per_page', 25);
        return DocumentResource::collection($documents->paginate($perPage));
    }

    public function store(StoreDocumentRequest $request): DocumentResource
    {
        $validatedData = $request->validated();
        $document = $this->documentService->createDocument($validatedData);

        return new DocumentResource($document->load('media'));
    }

    public function show(Document $document): DocumentResource
    {
        $document->load([
            'user',
            'supplier',
            'customer',
            'sourceWarehouse',
            'targetWarehouse',
            'parentDocument',
            'childDocuments',
            'items.productVariant',
            'items.taxRate',
            'media',
            'responsible', // Osoba odpowiedzialna
        ]);
        return new DocumentResource($document);
    }

    public function update(UpdateDocumentRequest $request, Document $document): DocumentResource
    {
        $validatedData = $request->validated();
        $updatedDocument = $this->documentService->updateDocument($document, $validatedData);

        return new DocumentResource($updatedDocument->load('media'));
    }

    public function close(Document $document): JsonResource
    {
        try {
            $closedDocument = $this->documentService->closeDocument($document);
            return new DocumentResource($closedDocument);
        } catch (Throwable $e) {
            Log::error("Błąd DocumentController@close: " . $e->getMessage(), ['document_id' => $document->id]);
            return response()->json(['message' => 'Błąd serwera podczas zamykania dokumentu: ' . $e->getMessage()], 500);
        }
    }

    public function linkFinancial(Request $request, Document $document): JsonResource
    {
        try {
            $invoiceData = $request->validate(['document_date' => 'sometimes|date']);
            $financialDocument = $this->documentService->linkFinancialDocument($document, $invoiceData);
            return new DocumentResource($financialDocument);
        } catch (Throwable $e) {
            Log::error("Błąd DocumentController@linkFinancial: " . $e->getMessage(), ['document_id' => $document->id]);
            return response()->json(['message' => 'Błąd serwera podczas tworzenia powiązanej faktury: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Document $document): Response
    {
        try {
            $this->documentService->deleteDocument($document);
            return response()->noContent();
        } catch (Throwable $e) {
            Log::error("Błąd DocumentController@destroy: " . $e->getMessage(), ['document_id' => $document->id]);
            return response()->json(['message' => 'Błąd serwera podczas usuwania dokumentu.'], 500);
        }
    }
}
