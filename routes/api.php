<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


//Route::get('/get-roles', function () {
//    set_time_limit(0);
//
//    $wsdl = 'https://pub-cix.ntsw.ir/services/InternalTradeServices?wsdl';
//
//    $clientOptions = [
//        'stream_context' => stream_context_create([
//            'ssl' => [
//                'verify_peer' => false,
//                'verify_peer_name' => false,
//                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
//            ]
//        ]),
//        'login' => 'internalservice',
//        'password' => 'ESBesb12?',
//        'trace' => true,
//        'keep_alive' => false,
//    ];
//
//    try {
//        $client = new \SoapClient($wsdl, $clientOptions);
//
//        $payload = [
//            'username' => 'Public_User',
//            'srvPass' => 'A32@sVy%f53Z#g3y',
//            'password_otpCode' => 'Pp@1234p1' , // 'Pp@1234p1'  'Ysi+KuwwX%LKB22]'
//            'PersonNationalCode' => '3256259561' , // '3256259561'   '0780420772'
//        ];
//
//        $response = $client->GetPersonRoles_WithPersonNationalCode_SI($payload);
//
//        return response()->json($response);
//
//    } catch (\SoapFault $e) {
//        return response()->json([
//            'error' => $e->getMessage(),
//            'trace' => isset($client) ? $client->__getLastResponse() : 'Client init failed'
//        ], 500);
//    }
//});
//
//Route::get('/test-auth', function () {
//    $client = new \SoapClient('https://pub-cix.ntsw.ir/services/InternalTradeServices?wsdl', [
//        'login' => 'internalservice',
//        'password' => 'ESBesb12?',
//        'authentication' => SOAP_AUTHENTICATION_BASIC,
//        'trace' => true,
//        'stream_context' => stream_context_create([
//            'ssl' => [
//                'verify_peer' => false,
//                'verify_peer_name' => false,
//                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
//            ]
//        ]),
//    ]);
//
//    try {
//        $res = $client->GetPersonRoles_WithPersonNationalCode_SI([
//            'username' => 'Public_User',
//            'srvPass' => 'A32@sVy%f53Z#g3y',
//            'password_otpCode' => 'Ysi+KuwwX%LKB22]',
//            'PersonNationalCode' => '10101663485',
//        ]);
//        return response()->json($res);
//    } catch (\Exception $e) {
//        return response()->json(['error' => $e->getMessage()], 500);
//    }
//});
//
//Route::get('/find-working-combo', function () {
//    set_time_limit(600);
//
//    $client = new \SoapClient('https://pub-cix.ntsw.ir/services/InternalTradeServices?wsdl', [
//        'login' => 'internalservice',
//        'password' => 'ESBesb12?',
//        'authentication' => SOAP_AUTHENTICATION_BASIC,
//        'trace' => true,
//        'cache_wsdl' => WSDL_CACHE_NONE,
//        'stream_context' => stream_context_create([
//            'ssl' => [
//                'verify_peer' => false,
//                'verify_peer_name' => false,
//                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
//            ]
//        ]),
//    ]);
//
//    $accounts = [
//        'company' => [
//            'id' => '10101663485',
//            'pass' => 'Ysi+KuwwX%LKB22]',
//            'roles' => [
//                ['role' => '102576', 'postal' => 1964914168, 'license' => 0, 'activity' => 1],
//                ['role' => '32770', 'postal' => 1964914168, 'license' => 0, 'activity' => 3],
//                ['role' => '2162788', 'postal' => 1964914168, 'license' => 0, 'activity' => 2],
//                ['role' => '2162789', 'postal' => 1964914168, 'license' => 11, 'activity' => 2],
//                ['role' => '2162790', 'postal' => 1964914168, 'license' => 0, 'activity' => 2],
//                ['role' => '2162810', 'postal' => 1964914168, 'license' => 0, 'activity' => 2],
//                ['role' => '2162818', 'postal' => 1964914168, 'license' => 11, 'activity' => 2],
//                ['role' => '2162826', 'postal' => 1964914168, 'license' => 0, 'activity' => 2],
//            ]
//        ],
//        'personal' => [
//            'id' => '3256259561',
//            'pass' => 'Pp@1234p1',
//            'roles' => [
//                ['role' => '102576', 'postal' => 1465775861, 'license' => 0, 'activity' => 1],
//                ['role' => '32770', 'postal' => 1465775861, 'license' => 0, 'activity' => 3],
//                ['role' => '2162788', 'postal' => 1465775861, 'license' => 0, 'activity' => 2],
//                ['role' => '2162789', 'postal' => 1465775861, 'license' => 11, 'activity' => 2],
//                ['role' => '2162790', 'postal' => 1465775861, 'license' => 0, 'activity' => 2],
//                ['role' => '2162810', 'postal' => 1465775861, 'license' => 0, 'activity' => 2],
//                ['role' => '2162818', 'postal' => 1465775861, 'license' => 11, 'activity' => 2],
//                ['role' => '2162826', 'postal' => 1465775861, 'license' => 0, 'activity' => 2],
//            ]
//        ]
//    ];
//
//    $sellTypes = [1, 2, 7, 11, 12, 13, 14, 15, 20];
//    $inOuts = [1, 2];
//    $working = [];
//
//    foreach ($accounts as $accType => $acc) {
//        foreach ($acc['roles'] as $roleData) {
//            foreach ($sellTypes as $sell) {
//                foreach ($inOuts as $io) {
//                    try {
//                        $res = $client->Get_InquiryDocumentsList_SI([
//                            'username' => 'Public_User',
//                            'srvPass' => 'A32@sVy%f53Z#g3y',
//                            'password_otpCode' => $acc['pass'],
//                            'PersonNationalCode' => $acc['id'],
//                            'UserRoleIDstr' => $roleData['role'],
//                            'UserRoleExtraFields' => [
//                                'PostalCode' => $roleData['postal'],
//                                'LicenseNumber' => $roleData['license'],
//                                'ActivityType' => $roleData['activity']
//                            ],
//                            'SellTypeIdStr' => $sell,
//                            'InOutStr' => $io,
//                            'StartDateStr' => '14030101',
//                            'EndDateStr' => '14041229',
//                            'StartIndex' => 0,
//                            'PageSize' => 1,
//                        ]);
//
//                        $result = $res->Get_InquiryDocumentsList_SIResult ?? null;
//                        if (!$result) continue;
//
//                        if ($result->ResultCode === 0 && ($result->TotalCount ?? 0) > 0) {
//                            $working[] = [
//                                'account' => $accType,
//                                'id' => $acc['id'],
//                                'role' => $roleData['role'],
//                                'sellType' => $sell,
//                                'inOut' => $io,
//                                'count' => $result->TotalCount,
//                            ];
//                        }
//
//                        usleep(100000);
//                    } catch (\Exception $e) {
//                        continue;
//                    }
//                }
//            }
//        }
//    }
//
//    return response()->json([
//        'found' => count($working),
//        'working_combinations' => $working,
//    ]);
//});
//
//Route::get('/simple-query', function () {
//    set_time_limit(0);
//
//    $options = [
//        'login' => 'internalservice',
//        'password' => 'ESBesb12?',
//        'authentication' => SOAP_AUTHENTICATION_BASIC,
//        'trace' => true,
//        'exceptions' => true,
//        'keep_alive' => false,
//        'cache_wsdl' => WSDL_CACHE_NONE,
//        'stream_context' => stream_context_create([
//            'ssl' => [
//                'verify_peer' => false,
//                'verify_peer_name' => false,
//                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
//            ]
//        ]),
//    ];
//
//    $client = new \SoapClient('https://pub-cix.ntsw.ir/services/InternalTradeServices?wsdl', $options);
//
//    $sellTypes = [1, 2, 3, 4, 5];
//    $inOuts = [1, 2];
//    $results = [];
//
//    foreach ($inOuts as $inOut) {
//        foreach ($sellTypes as $sellType) {
//            try {
//                $payload = [
//                    'username' => 'Public_User',
//                    'srvPass' => 'A32@sVy%f53Z#g3y',
//                    'password_otpCode' => 'Pp@1234p1',
//                    'PersonNationalCode' => '10101663485',
//                    'UserRoleIDstr' => '2162826',
//                    'UserRoleExtraFields' => [
//                        'PostalCode' => 1465775861,
//                        'LicenseNumber' => 0,
//                        'ActivityType' => 1
//                    ],
//                    'SellTypeIdStr' => $sellType,
//                    'InOutStr' => $inOut,
//                    'StartDateStr' => '14040101',
//                    'EndDateStr' => '14041029',
//                    'StartIndex' => 0,
//                    'PageSize' => 1,
//                ];
//
//                $response = $client->Get_InquiryDocumentsList_SI($payload);
//                $result = $response->Get_InquiryDocumentsList_SIResult ?? null;
//
//                $resultCode = $result->ResultCode ?? 'NULL';
//                $hasData = isset($result->ObjList) && $result->ObjList !== null;
//                $count = $result->TotalCount ?? 0;
//
//                if ($resultCode === 0 && $hasData) {
//                    $results[] = [
//                        'status' => '✅ HAS DATA',
//                        'sellType' => $sellType,
//                        'inOut' => $inOut,
//                        'count' => $count,
//                    ];
//                } elseif ($resultCode === 0) {
//                    $results[] = [
//                        'status' => '⚠️ NO DATA',
//                        'sellType' => $sellType,
//                        'inOut' => $inOut,
//                    ];
//                } else {
//                    $results[] = [
//                        'status' => '❌ ERROR',
//                        'sellType' => $sellType,
//                        'inOut' => $inOut,
//                        'code' => $resultCode,
//                        'message' => $result->ResultMessage ?? 'NULL',
//                    ];
//                }
//
//                usleep(250000);
//
//            } catch (\Exception $e) {
//                $results[] = [
//                    'status' => '❌ EXCEPTION',
//                    'sellType' => $sellType,
//                    'inOut' => $inOut,
//                    'error' => $e->getMessage(),
//                ];
//            }
//        }
//    }
//
//    $withData = array_filter($results, fn($r) => str_contains($r['status'], 'HAS DATA'));
//
//    return response()->json([
//        'total_tested' => count($results),
//        'with_data' => count($withData),
//        'successful_combinations' => $withData,
//        'all_results' => $results,
//    ]);
//});
//

