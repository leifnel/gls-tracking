<?php
/**
 * TrackingApi.php
 *
 * @author dbojdo - Daniel Bojdo <daniel.bojdo@web-it.eu>
 * Created on Nov 24, 2014, 15:47
 */

namespace Webit\GlsTracking\Api;

use JMS\Serializer\SerializationContext;
use Webit\GlsTracking\Api\Exception\AuthException;
use Webit\GlsTracking\Api\Exception\Exception;
use Webit\GlsTracking\Api\Exception\GlsApiCommunicationException;
use Webit\GlsTracking\Api\Exception\GlsTrackingApiException;
use Webit\GlsTracking\Api\Exception\NoDataFoundException;
use Webit\GlsTracking\Api\Exception\UnknownErrorCodeException;
use Webit\GlsTracking\Model\DateTime;
use Webit\GlsTracking\Model\ExitCode;
use Webit\GlsTracking\Model\Message\AbstractRequest;
use Webit\GlsTracking\Model\Message\TuDetailsRequest;
use Webit\GlsTracking\Model\Message\TuDetailsResponse;
use Webit\GlsTracking\Model\Message\TuListRequest;
use Webit\GlsTracking\Model\Message\TuListResponse;
use Webit\GlsTracking\Model\Message\TuPODRequest;
use Webit\GlsTracking\Model\Message\TuPODResponse;
use Webit\GlsTracking\Model\Parameters;
use Webit\GlsTracking\Model\UserCredentials;
use Webit\SoapApi\SoapApiExecutorInterface;

/**
 * Class TrackingApi
 * @package Webit\GlsTracking\Api
 */
class TrackingApi
{

    /**
     * @var SoapApiExecutorInterface
     */
    private $executor;

    /**
     * @var UserCredentials
     */
    private $credentials;

    /**
     * @param SoapApiExecutorInterface $executor
     * @param UserCredentials $credentials
     */
    public function __construct(SoapApiExecutorInterface $executor, UserCredentials $credentials)
    {
        $this->executor = $executor;
        $this->credentials = $credentials;
    }

    private function doRequest($soapFunction, AbstractRequest $request, $outputType = 'ArrayCollection')
    {
        $this->applyCredentials($request);

        $request = array($soapFunction => $request);

        /** @var AbstractRequest $response */
        $response = $this->executor->executeSoapFunction($soapFunction, $request, $outputType);
        if ($response->getExitCode()->isSuccessfully() == false) {
            throw $this->createException($response->getExitCode());
        }

        return $response;
    }

    /**
     * @param $reference
     * @param string $language
     * @return TuDetailsResponse
     */
    public function getParcelDetails($reference, $language = 'EN')
    {
        /** @var TuDetailsResponse $response */
        $response = $this->doRequest(
            'GetTuDetail',
            new TuDetailsRequest($this->filterReferenceNo($reference), new Parameters('LangCode', $language)),
            'Webit\GlsTracking\Model\Message\TuDetailsResponse'
        );

        return $response;
    }

    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @param null $reference
     * @param null $customerReference
     * @param string $language
     * @throws Exception
     * @throws GlsTrackingApiException
     * @return TuListResponse
     */
    public function getParcelList(\DateTime $from, \DateTime $to, $reference = null, $customerReference = null, $language = 'EN')
    {
        /** @var TuListResponse $response */
        $response = $this->doRequest(
            'GetTuList',
            new TuListRequest(
                DateTime::fromDateTime($from),
                DateTime::fromDateTime($to),
                $reference ? $this->filterReferenceNo($reference) : null,
                $customerReference,
                new Parameters('LangCode', $language)
            ),
            'Webit\GlsTracking\Model\Message\TuListResponse'
        );

        return $response;
    }

    /**
     * @param $reference
     * @param string $language
     * @throws Exception
     * @throws GlsTrackingApiException
     * @return TuPODResponse
     */
    public function getProofOfDelivery($reference, $language = 'EN')
    {

        /** @var TuPODResponse $response */
        $response = $this->doRequest(
            'GetTuPOD',
            new TuPODRequest($this->filterReferenceNo($reference), new Parameters('LangCode', $language)),
            'Webit\GlsTracking\Model\Message\TuPODResponse'
        );

        return $response;
    }

    /**
     * @param AbstractRequest $request
     */
    private function applyCredentials(AbstractRequest $request)
    {
        $request->setCredentials($this->credentials);
    }

    /**
     * @param ExitCode $exitCode
     * @return GlsTrackingApiException
     */
    private function createException(ExitCode $exitCode)
    {
        switch ($exitCode->getCode()) {
            case ExitCode::CODE_AUTHENTICATION_ERROR:
                return new AuthException($exitCode->getDescription(), $exitCode->getCode());
            case ExitCode::CODE_NO_DATA_FOUND:
                return new NoDataFoundException($exitCode->getDescription(), $exitCode->getCode());
        }

        return new UnknownErrorCodeException(sprintf(
            'Unknown error given with code "%s" and message "%s"', $exitCode->getCode(), $exitCode->getDescription()
        ));
    }

    /**
     * @param string $referenceNo
     * @return string
     */
    private function filterReferenceNo($referenceNo)
    {
        return substr($referenceNo, 0, 11);
    }
}
