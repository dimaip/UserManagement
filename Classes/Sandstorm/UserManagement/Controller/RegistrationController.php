<?php
namespace Sandstorm\UserManagement\Controller;

use Sandstorm\UserManagement\Domain\Model\RegistrationFlow;
use Sandstorm\UserManagement\Domain\Repository\RegistrationFlowRepository;
use Sandstorm\UserManagement\Domain\Service\EmailService;
use Sandstorm\UserManagement\Domain\Service\UserCreationServiceInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\Controller\ActionController;

/**
 * Do the actual registration of new users
 */
class RegistrationController extends ActionController
{

    /**
     * @Flow\Inject
     * @var RegistrationFlowRepository
     */
    protected $registrationFlowRepository;

    /**
     * @Flow\Inject
     * @var UserCreationServiceInterface
     */
    protected $userCreationService;

    /**
     * @Flow\Inject
     * @var EmailService
     */
    protected $emailService;

    /**
     * @var string
     * @Flow\InjectConfiguration(path="email.senderAddress")
     */
    protected $emailSenderAddress;

    /**
     * @var string
     * @Flow\InjectConfiguration(path="email.senderName")
     */
    protected $emailSenderName;

    /**
     * @var string
     * @Flow\InjectConfiguration(path="email.subjectActivation")
     */
    protected $subjectActivation;

    /**
     * @var string
     * @Flow\InjectConfiguration(path="email.confirmationAddress")
     */
    protected $emailConfirmationAddress;

    /**
     * @var string
     * @Flow\InjectConfiguration(path="email.confirmationName")
     */
    protected $emailConfirmationName;

    /**
     * @var string
     * @Flow\InjectConfiguration(path="email.subjectConfirmation")
     */
    protected $subjectConfirmation;


    /**
     * @Flow\SkipCsrfProtection
     */
    public function indexAction()
    {
    }

    /**
     * @param RegistrationFlow $registrationFlow
     */
    public function registerAction(RegistrationFlow $registrationFlow)
    {
        // We remove already existing flows
        $alreadyExistingFlows = $this->registrationFlowRepository->findByEmail($registrationFlow->getEmail());
        if (count($alreadyExistingFlows) > 0) {
            foreach ($alreadyExistingFlows as $alreadyExistingFlow) {
                $this->registrationFlowRepository->remove($alreadyExistingFlow);
            }
        }
        $registrationFlow->storeEncryptedPassword();

        // Send out a confirmation mail
        $activationLink = $this->uriBuilder->reset()->setCreateAbsoluteUri(true)->uriFor(
            'activateAccount',
            ['token' => $registrationFlow->getActivationToken()],
            'Registration');

        $this->emailService->sendTemplateBasedEmail(
            'ActivationToken',
            $this->subjectActivation,
            [$this->emailSenderAddress => $this->emailSenderName],
            [$this->emailConfirmationAddress => $this->emailConfirmationName],
            [
                'activationLink' => $activationLink,
                'applicationName' => $this->emailSenderName,
                'registrationFlow' => $registrationFlow,
                // BaseUri can be used to embed resources (images) into the email
                'baseUri' => htmlspecialchars($this->controllerContext->getRequest()->getHttpRequest()->getBaseUri())
            ]
        );

        $this->registrationFlowRepository->add($registrationFlow);

        $this->view->assign('email', $registrationFlow->getEmail());
    }

    /**
     * @param string $token
     */
    public function activateAccountAction($token)
    {
        /* @var $registrationFlow \Sandstorm\UserManagement\Domain\Model\RegistrationFlow */
        $registrationFlow = $this->registrationFlowRepository->findOneByActivationToken($token);
        if (!$registrationFlow) {
            $this->view->assign('tokenNotFound', true);
            return;
        }

        if (!$registrationFlow->hasValidActivationToken()) {
            $this->view->assign('tokenTimeout', true);
            return;
        }

        // Send out a confirmation mail
        $confirmationLink = $this->uriBuilder->reset()->setCreateAbsoluteUri(TRUE)->uriFor(
            'confirmAccount',
            ['token' => $registrationFlow->getConfirmationToken()],
            'Registration');

        $this->emailService->sendTemplateBasedEmail(
            'ConfirmationToken',
            $this->subjectActivation,
            [$this->emailSenderAddress => $this->emailSenderName],
            [$registrationFlow->getEmail()],
            [
                'confirmationLink' => $confirmationLink,
                'applicationName' => $this->emailSenderName,
                'registrationFlow' => $registrationFlow,
                // BaseUri can be used to embed resources (images) into the email
                'baseUri' => htmlspecialchars($this->controllerContext->getRequest()->getHttpRequest()->getBaseUri())
            ]
        );

        $this->view->assign('success', true);
    }

    /**
     * @param string $token
     */
    public function confirmAccountAction($token)
    {
        /* @var $registrationFlow \Sandstorm\UserManagement\Domain\Model\RegistrationFlow */
        $registrationFlow = $this->registrationFlowRepository->findOneByConfirmationToken($token);
        if (!$registrationFlow) {
            $this->view->assign('tokenNotFound', true);
            return;
        }

        if (!$registrationFlow->hasValidConfirmationToken()) {
            $this->view->assign('tokenTimeout', true);
            return;
        }

        $this->userCreationService->createUserAndAccount($registrationFlow);
        $this->registrationFlowRepository->remove($registrationFlow);
        $this->persistenceManager->whitelistObject($registrationFlow);

        $this->view->assign('success', true);
    }

    /**
     * Disable the technical error flash message
     *
     * @return boolean
     */
    protected function getErrorFlashMessage()
    {
        return false;
    }
}
