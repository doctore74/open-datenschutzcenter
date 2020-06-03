<?php
/**
 * Created by PhpStorm.
 * User: andreas.holzmann
 * Date: 15.05.2020
 * Time: 09:15
 */

namespace App\Controller;

use App\Entity\AuditTom;
use App\Entity\AuditTomAbteilung;
use App\Entity\AuditTomStatus;
use App\Entity\AuditTomZiele;
use App\Form\Type\AuditTomType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuditTomController extends AbstractController
{
    /**
     * @Route("/audit-tom", name="audit_tom")
     */
    public function index()
    {
        $audit = $this->getDoctrine()->getRepository(AuditTom::class)->findAllByTeam($this->getUser()->getTeam());
        return $this->render('audit_tom/index.html.twig', [
            'audit' => $audit,
        ]);
    }

    /**
     * @Route("/audit-tom/new", name="audit_tom_new")
     */
    public function addAuditTom(ValidatorInterface $validator, Request $request)
    {
        $team = $this->getUser()->getTeam();
        if ($team === null) {
            return $this->redirectToRoute('fos_user_security_logout');
        }

        $today = new \DateTime();
        $audit = new AuditTom();
        $audit->setTeam($team);
        $audit->setNummer('AUDIT-'. hexdec( uniqid() ));
        $audit->setActiv(true);
        $audit->setCreatedAt($today);
        $audit->setUser($this->getUser());
        $status = $this->getDoctrine()->getRepository(AuditTomStatus::class)->findAll();
        $ziele = $this->getDoctrine()->getRepository(AuditTomZiele::class)->findAllTagetsByTeam($team);
        $abteilungen = $this->getDoctrine()->getRepository(AuditTomAbteilung::class)->findAllByTeam($team);

        $form = $this->createForm(AuditTomType::class, $audit, ['abteilungen' => $abteilungen, 'ziele' => $ziele, 'status' => $status]);
        $form->handleRequest($request);

        $errors = array();
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $errors = $validator->validate($data);
            if (count($errors) == 0) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($data);
                $em->flush();
                return $this->redirectToRoute('audit_tom');
            }
        }
        return $this->render('audit_tom/new.html.twig', [
            'form' => $form->createView(),
            'errors' => $errors,
            'title' => 'A-Frage erstellen',
            'audit' => $audit,
            'activNummer' => true,
            'activ' => $audit->getActiv()
        ]);
    }

    /**
     * @Route("/audit-tom/edit", name="audit_tom_edit")
     */
    public function EditAuditTom(ValidatorInterface $validator, Request $request)
    {
        $team = $this->getUser()->getTeam();
        if ($team === null) {
            return $this->redirectToRoute('dashboard');
        }
        $today = new \DateTime();
        $audit = $this->getDoctrine()->getRepository(AuditTom::class)->find($request->get('tom'));
        $status = $this->getDoctrine()->getRepository(AuditTomStatus::class)->findAll();
        $abteilungen = $this->getDoctrine()->getRepository(AuditTomAbteilung::class)->findAllByTeam($team);
        $ziele = $this->getDoctrine()->getRepository(AuditTomZiele::class)->findAllTagetsByTeam($team);

        //Sicherheitsfunktion, dass nur eigene und Default TOMs bearbeitet werden können
        if ($audit->getTeam() !== $team) {
            return $this->redirectToRoute('audit_tom');
        }

        $allAudits = array_reverse($this->getDoctrine()->getRepository(AuditTom::class)->findAllByTeam($this->getUser()->getTeam()));

        $mykey = 0;
        foreach ($allAudits as $key=>$item) {
            if ($item === $audit) {
                $mykey = $key;
            }
        }
        try {
            $nextAudit = $allAudits[++$mykey];
        }
        catch (\Exception $e) {
            $nextAudit = $allAudits[0];
        }


        $newAudit = clone $audit;
        $newAudit->setPrevious($audit);
        $newAudit->setCreatedAt($today);
        $newAudit->setUser($this->getUser());
        $newAudit->setTeam($team);
        $form = $this->createForm(AuditTomType::class, $newAudit, ['abteilungen' => $abteilungen, 'ziele' => $ziele, 'status' => $status]);
        $form->remove('nummer');
        $form->handleRequest($request);
        $errors = array();
        if ($form->isSubmitted() && $form->isValid()) {
            $audit->setActiv(false);
            $newAudit = $form->getData();
            $errors = $validator->validate($newAudit);
            if (count($errors) == 0) {

                $em = $this->getDoctrine()->getManager();
                $em->persist($newAudit);
                $em->persist($audit);
                $em->flush();
                return $this->redirectToRoute('audit_tom_edit', array('tom'=>$newAudit->getId()));
            }
        }
        return $this->render('audit_tom/edit.html.twig', [
            'form' => $form->createView(),
            'errors' => $errors,
            'title' => 'A-Frage bearbeiten',
            'audit' => $audit,
            'activ' => $audit->getActiv(),
            'activNummer' => false,
            'nextAudit' => $nextAudit,
        ]);
    }

    /**
     * @Route("/audit-tom/clone", name="audit_tom_clone")
     */
    public function CloneAuditTom(Request $request)
    {
        $team = $this->getUser()->getTeam();
        if ($team === null) {
            return $this->redirectToRoute('dashboard');
        }
        $today = new \DateTime();
        $audit = $this->getDoctrine()->getRepository(AuditTom::class)->findAllByTeam(1);

        $em = $this->getDoctrine()->getManager();

        foreach ($audit as $data) {
            if ($data->getCreatedAt() > $team->getClonedAt()) {
                $newAudit = clone $data;
                $newAudit->setTeam($team);
                $newAudit->setCreatedAt($today);
                $em->persist($newAudit);
            }

        }

        //set ClonedAt Date to be able to update later newer versions
        $team->setclonedAt($today);

        $em->persist($team);
        $em->flush();

        return $this->redirectToRoute('audit_tom');

    }
}
