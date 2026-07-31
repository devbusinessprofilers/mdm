<?php
declare(strict_types=1);
namespace App\Pim\Command;
use App\Pim\Entity\Activite\Activite; use App\Pim\Validation\ValidationGroups; use Doctrine\ORM\EntityManagerInterface; use Symfony\Component\Console\Attribute\AsCommand; use Symfony\Component\Console\Command\Command; use Symfony\Component\Console\Input\InputInterface; use Symfony\Component\Console\Input\InputOption; use Symfony\Component\Console\Output\OutputInterface; use Symfony\Component\Console\Style\SymfonyStyle; use Symfony\Component\Validator\Validator\ValidatorInterface;
#[AsCommand(name:'app:activites:validate',description:'Contrôle les fiches Activité existantes sans modifier les données.')]
final class ValidateActivitesCommand extends Command
{
public function __construct(private readonly EntityManagerInterface $em,private readonly ValidatorInterface $validator){parent::__construct();}
protected function configure():void{$this->addOption('submission',null,InputOption::VALUE_NONE,'Ajoute les contraintes nécessaires à la soumission.');}
protected function execute(InputInterface $input,OutputInterface $output):int{$io=new SymfonyStyle($input,$output);$groups=[ValidationGroups::DRAFT];if($input->getOption('submission')){$groups[]=ValidationGroups::SUBMISSION;}$invalid=$checked=0;foreach($this->em->createQuery('SELECT a FROM '.Activite::class.' a ORDER BY a.id')->toIterable() as $a){if(!$a instanceof Activite){continue;}++$checked;$violations=$this->validator->validate($a,null,$groups);if(0===count($violations)){continue;}++$invalid;foreach($violations as $violation){$io->writeln(json_encode(['id'=>$a->id(),'code'=>$a->code(),'field'=>(string)$violation->getPropertyPath(),'error'=>(string)$violation->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}$this->em->detach($a);}$io->comment(sprintf('%d activité(s) contrôlée(s), %d invalide(s). Aucune donnée modifiée.',$checked,$invalid));return 0===$invalid?Command::SUCCESS:Command::FAILURE;}
}
