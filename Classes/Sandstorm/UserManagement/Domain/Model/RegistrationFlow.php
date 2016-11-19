<?php
namespace Sandstorm\UserManagement\Domain\Model;

use TYPO3\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;
use TYPO3\Flow\Exception;
use TYPO3\Flow\Object\ObjectManagerInterface;
use TYPO3\Flow\Utility\Algorithms;

/**
 * @Flow\Entity
 */
class RegistrationFlow
{
    /**
     * @var string
     * @Flow\Validate(type="NotEmpty")
     * @Flow\Validate(type="EmailAddress")
     */
    protected $email;

    /**
     * @var string
     */
    protected $encryptedPassword;

    /**
     * @Flow\Transient
     * @var PasswordDto
     * @Flow\Validate(type="Sandstorm\UserManagement\Domain\Validator\CustomPasswordDtoValidator", validationGroups={"Controller"})
     */
    protected $passwordDto;

    /**
     * @var array
     * @ORM\Column(type="json_array")
     */
    protected $attributes = [];

    /**
     * @var string
     * @ORM\Column(nullable=TRUE)
     */
    protected $activationToken;

    /**
     * @var \DateTime
     * @ORM\Column(nullable=TRUE)
     */
    protected $activationTokenValidUntil;

    /**
     * @var string
     * @Flow\Transient
     * @Flow\InjectConfiguration(path="activationTokenTimeout")
     */
    protected $activationTokenTimeout;

    /**
     * @var string
     * @ORM\Column(nullable=TRUE)
     */
    protected $confirmationToken;

    /**
     * @var \DateTime
     * @ORM\Column(nullable=TRUE)
     */
    protected $confirmationTokenValidUntil;

    /**
     * @var string
     * @Flow\Transient
     * @Flow\InjectConfiguration(path="confirmationTokenTimeout")
     */
    protected $confirmationTokenTimeout;

    /**
     * @param $cause int The cause of the object initialization.
     * @see http://flowframework.readthedocs.org/en/stable/TheDefinitiveGuide/PartIII/ObjectManagement.html#lifecycle-methods
     * @throws Exception
     */
    public function initializeObject($cause)
    {
        if ($cause === ObjectManagerInterface::INITIALIZATIONCAUSE_CREATED) {
            $this->generateActivationToken();
            $this->generateConfirmationToken();
        }
    }

    /**
     * @param PasswordDto $passwordDto
     */
    public function setPasswordDto(PasswordDto $passwordDto)
    {
        $this->passwordDto = $passwordDto;
    }

    /**
     * Generate a new activation token
     *
     * @throws Exception If the user has an account already
     */
    public function generateActivationToken()
    {
        $this->activationToken = Algorithms::generateRandomString(30);
        $this->activationTokenValidUntil = (new \DateTime())->add(\DateInterval::createFromDateString($this->activationTokenTimeout));
    }

    /**
     * Check if the user has a valid activation token.
     *
     * @return bool
     */
    public function hasValidActivationToken()
    {
        if ($this->activationTokenValidUntil == null) {
            return false;
        }

        return $this->activationTokenValidUntil->getTimestamp() > time();
    }

    /**
     * Generate a new confirmation token
     * @throws Exception If the user has an account already
     */
    public function generateConfirmationToken()
    {
        $this->confirmationToken = Algorithms::generateRandomString(30);
        $this->confirmationTokenValidUntil = (new \DateTime())->add(\DateInterval::createFromDateString($this->confirmationTokenTimeout));
    }

    /**
     * Check if the user has a valid confirmation token.
     * @return bool
     */
    public function hasValidConfirmationToken()
    {
        if ($this->confirmationTokenValidUntil == NULL) {
            return FALSE;
        }
        return $this->confirmationTokenValidUntil->getTimestamp() > time();
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param array $attributes
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    public function storeEncryptedPassword()
    {
        $this->encryptedPassword = $this->passwordDto->getEncryptedPasswordAndRemoveNonencryptedVersion();
    }

    /**
     * @return string
     */
    public function getEncryptedPassword()
    {
        return $this->encryptedPassword;
    }

    /**
     * @return string
     */
    public function getActivationToken()
    {
        return $this->activationToken;
    }

    /**
     * @return string
     */
    public function getConfirmationToken()
    {
        return $this->confirmationToken;
    }
}
